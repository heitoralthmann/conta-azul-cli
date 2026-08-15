<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class CallbackServer
{
    /**
     * Browsers open speculative sockets alongside the navigation and sometimes
     * never send anything on them. Waiting on one must not eat the whole login
     * window, so a single connection only gets this long to prove itself.
     */
    private const CONNECTION_READ_TIMEOUT = 5;

    /**
     * Enough for any request line a browser will produce; a peer that keeps
     * talking past it is not the callback we are waiting for.
     */
    private const MAX_REQUEST_LINE_BYTES = 16384;

    public function __construct(
        private readonly int     $port           = 9876,
        // Login is interactive: the operator still has to open a browser, sign in
        // and possibly clear MFA. 120s was routinely too short in practice.
        private readonly int     $timeoutSeconds = 300,
        private readonly ?string $certFile       = null,
        private readonly ?string $keyFile        = null,
    ) {
    }

    public function waitForCallback(string $expectedState): string
    {
        $errno  = null;
        $errstr = null;
        $server = $this->openServer($errno, $errstr);
        if ($server === false) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'Não foi possível iniciar o servidor local na porta ' . $this->port . ': ' . ($errstr ?? '') . ' (' . ($errno ?? 0) . '). Verifique se a porta está livre.',
            );
        }

        try {
            return $this->acceptUntilCallback($server, $expectedState);
        } finally {
            fclose($server);
        }
    }

    /**
     * A browser does not open exactly one connection per navigation: it also
     * preconnects, retries and asks for /favicon.ico. Serving only the first
     * socket meant a silent one could block the exchange until the socket
     * timed out, and by then the authorization code had aged badly. So keep
     * accepting until a connection actually carries the callback.
     *
     * @param resource $server
     */
    private function acceptUntilCallback(mixed $server, string $expectedState): string
    {
        $deadline = microtime(true) + $this->timeoutSeconds;

        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new CliException(
                    ErrorKind::ClientError,
                    false,
                    "Timeout aguardando callback OAuth ({$this->timeoutSeconds}s). "
                    . 'Rode "ca auth login" novamente e tenha o navegador pronto, '
                    . 'ou aumente a janela com CA_CALLBACK_TIMEOUT.',
                );
            }

            // Silenced: a timeout is an expected outcome handled above, and the
            // PHP warning would otherwise leak into stdout, which the output
            // contract reserves for the JSON payload. A false here also covers
            // a failed TLS handshake on a speculative socket — both just mean
            // "nothing useful yet", so the loop moves on.
            $conn = @stream_socket_accept($server, $remaining);
            if ($conn === false) {
                continue;
            }

            try {
                $requestLine = $this->readRequestLine($conn);
                if ($requestLine === null || !preg_match('/GET \/?\S*callback\?([^ ]+)/', $requestLine, $m)) {
                    $this->respond($conn, '404 Not Found', '<p>Requisição ignorada.</p>');

                    continue;
                }

                $this->respond(
                    $conn,
                    '200 OK',
                    '<script>window.close()</script><p>Autenticação concluída. Pode fechar esta aba.</p>',
                );
            } finally {
                fclose($conn);
            }

            return $this->extractCode($m[1], $expectedState);
        }
    }

    /**
     * Only the request line is read, never the full header block. Chrome sends
     * more than a kilobyte of headers, and a second blocking fread() on a TLS
     * stream can stall even with the rest already decrypted and waiting — which
     * is precisely what used to freeze the browser mid-redirect. The request
     * line arrives in the first read and carries everything we need.
     *
     * @param resource $conn
     */
    private function readRequestLine(mixed $conn): ?string
    {
        stream_set_timeout($conn, self::CONNECTION_READ_TIMEOUT);

        $buffer = '';
        while (!str_contains($buffer, "\r\n") && strlen($buffer) < self::MAX_REQUEST_LINE_BYTES) {
            $chunk = fread($conn, 8192);
            $meta  = stream_get_meta_data($conn);
            if ($chunk === false || $chunk === '' || $meta['timed_out'] || feof($conn)) {
                $buffer .= is_string($chunk) ? $chunk : '';

                break;
            }
            $buffer .= $chunk;
        }

        $line = strtok($buffer, "\r\n");

        return $line === false ? null : $line;
    }

    private function extractCode(string $query, string $expectedState): string
    {
        /** @var array<string, mixed> $params */
        $params = [];
        parse_str($query, $params);

        $stateParam = $params['state'] ?? '';
        if (!is_string($stateParam) || $stateParam !== $expectedState) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'State OAuth inválido — possível ataque CSRF. Tente fazer login novamente.',
            );
        }

        $code = $params['code'] ?? '';
        if (!is_string($code) || $code === '') {
            $rawError = $params['error'] ?? 'desconhecido';
            $error    = is_string($rawError) ? $rawError : 'desconhecido';
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                "Login negado ou erro retornado pelo servidor de autorização: {$error}",
            );
        }

        return $code;
    }

    /** @param resource $conn */
    private function respond(mixed $conn, string $status, string $body): void
    {
        @fwrite(
            $conn,
            "HTTP/1.1 {$status}\r\nContent-Type: text/html; charset=utf-8\r\nConnection: close\r\n\r\n" .
            "<html><body>{$body}</body></html>",
        );
    }

    /** @return resource|false */
    private function openServer(?int &$errno, ?string &$errstr): mixed
    {
        if ($this->certFile !== null && $this->keyFile !== null) {
            $context = stream_context_create([
                'ssl' => [
                    'local_cert'        => $this->certFile,
                    'local_pk'          => $this->keyFile,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ]);

            return @stream_socket_server(
                "ssl://127.0.0.1:{$this->port}",
                $errno,
                $errstr,
                STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
                $context,
            );
        }

        return @stream_socket_server("tcp://127.0.0.1:{$this->port}", $errno, $errstr);
    }
}
