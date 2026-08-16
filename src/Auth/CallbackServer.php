<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

/** Receives and validates the local OAuth redirect from a browser. */
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


    /**
     * Creates a callback listener.
     *
     * @param int $port Local TCP/TLS port.
     * @param int $timeoutSeconds Maximum time to wait for a valid callback.
     * @param string|null $certFile Optional TLS certificate path.
     * @param string|null $keyFile Optional TLS private key path.
     */
    public function __construct(
        private readonly int $port=9876,
        // Login is interactive: the operator still has to open a browser, sign in
        // and possibly clear MFA. 120s was routinely too short in practice.
        private readonly int $timeoutSeconds=300,
        private readonly ?string $certFile=NULL,
        private readonly ?string $keyFile=NULL,
    ) {
    }


    /** Waits until a callback with the expected CSRF state is received. */
    public function waitForCallback(string $expectedState): string {
        $errno  = NULL;
        $errstr = NULL;
        $server = $this->openServer($errno, $errstr);
        if ($server === FALSE) {
            throw new CliException(
              ErrorKind::ClientError,
              FALSE,
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
    private function acceptUntilCallback(mixed $server, string $expectedState): string {
        $deadline = microtime(TRUE) + $this->timeoutSeconds;

        while (TRUE) {
            $remaining = $deadline - microtime(TRUE);
            if ($remaining <= 0) {
                throw new CliException(
                  ErrorKind::ClientError,
                  FALSE,
                  "Timeout aguardando callback OAuth ({$this->timeoutSeconds}s). " . 'Rode "ca auth login" novamente e tenha o navegador pronto, ' . 'ou aumente a janela com CA_CALLBACK_TIMEOUT.',
                );
            }

            // Silenced: a timeout is an expected outcome handled above, and the
            // PHP warning would otherwise leak into stdout, which the output
            // contract reserves for the JSON payload. A false here also covers
            // a failed TLS handshake on a speculative socket — both just mean
            // "nothing useful yet", so the loop moves on.
            // phpcs:ignore Generic.PHP.NoSilencedErrors -- see comment above.
            $conn = @stream_socket_accept($server, $remaining);
            if ($conn === FALSE) {
                continue;
            }

            try {
                $requestLine = $this->readRequestLine($conn);
                if ($requestLine === NULL || !preg_match('/GET \/?\S*callback\?([^ ]+)/', $requestLine, $m)) {
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
    private function readRequestLine(mixed $conn): ?string {
        stream_set_timeout($conn, self::CONNECTION_READ_TIMEOUT);

        $buffer = '';
        $length = 0;
        while (!str_contains($buffer, "\r\n") && $length < self::MAX_REQUEST_LINE_BYTES) {
            $chunk = fread($conn, 8192);
            $meta  = stream_get_meta_data($conn);
            if ($chunk === FALSE || $chunk === '' || $meta['timed_out'] || feof($conn)) {
                $buffer .= is_string($chunk) ? $chunk : '';

                break;
            }
            $buffer .= $chunk;
            $length += strlen($chunk);
        }

        $line = strtok($buffer, "\r\n");

        return $line === FALSE ? NULL : $line;
    }


    /** Extracts and validates the authorization code from a callback query. */
    private function extractCode(string $query, string $expectedState): string {
        /** @var array<string, mixed> $params */
        $params = [];
        parse_str($query, $params);

        $stateParam = $params['state'] ?? '';
        if (!is_string($stateParam) || $stateParam !== $expectedState) {
            throw new CliException(
              ErrorKind::ClientError,
              FALSE,
              'State OAuth inválido — possível ataque CSRF. Tente fazer login novamente.',
            );
        }

        $code = $params['code'] ?? '';
        if (!is_string($code) || $code === '') {
            $rawError = $params['error'] ?? 'desconhecido';
            $error    = is_string($rawError) ? $rawError : 'desconhecido';
            throw new CliException(
              ErrorKind::AuthFailed,
              FALSE,
              "Login negado ou erro retornado pelo servidor de autorização: {$error}",
            );
        }

        return $code;
    }


    /**
     * Writes the minimal HTML response used to close or reject a browser tab.
     *
     * @param resource $conn
     */
    private function respond(mixed $conn, string $status, string $body): void {
        // Silenced: the client may have already dropped the connection (e.g. a
        // speculative preconnect), and a failed write here is not actionable —
        // the caller has nothing further to send.
        // phpcs:ignore Generic.PHP.NoSilencedErrors
        @fwrite(
          $conn,
          "HTTP/1.1 {$status}\r\nContent-Type: text/html; charset=utf-8\r\nConnection: close\r\n\r\n" . "<html><body>{$body}</body></html>",
        );
    }


    /** @return resource|false */
    private function openServer(?int &$errno, ?string &$errstr): mixed {
        // Silenced: bind/listen failures (e.g. port already in use) are reported
        // through $errno/$errstr and handled by the caller, so the PHP warning
        // would only be noise duplicating that.
        if ($this->certFile !== NULL && $this->keyFile !== NULL) {
            $context = stream_context_create(
              [
                'ssl' => [
                    'local_cert'        => $this->certFile,
                    'local_pk'          => $this->keyFile,
                    'verify_peer'       => FALSE,
                    'verify_peer_name'  => FALSE,
                ],
              ]
            );

            // phpcs:ignore Generic.PHP.NoSilencedErrors
            return @stream_socket_server(
              "ssl://127.0.0.1:{$this->port}",
              $errno,
              $errstr,
              STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
              $context,
            );
        }

        // phpcs:ignore Generic.PHP.NoSilencedErrors
        return @stream_socket_server("tcp://127.0.0.1:{$this->port}", $errno, $errstr);
    }


}
