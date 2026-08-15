<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class CallbackServer
{
    public function __construct(
        private readonly int     $port           = 9876,
        private readonly int     $timeoutSeconds = 120,
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
            $conn = stream_socket_accept($server, (float) $this->timeoutSeconds);
            if ($conn === false) {
                throw new CliException(
                    ErrorKind::ClientError,
                    false,
                    'Timeout aguardando callback OAuth. Tente novamente.',
                );
            }

            $request = '';
            while (!feof($conn) && !str_contains($request, "\r\n\r\n")) {
                $chunk = fread($conn, 1024);
                if ($chunk === false) {
                    break;
                }
                $request .= $chunk;
            }

            fwrite(
                $conn,
                "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nConnection: close\r\n\r\n" .
                "<html><body><script>window.close()</script><p>Autenticação concluída. Pode fechar esta aba.</p></body></html>",
            );
            fclose($conn);
        } finally {
            fclose($server);
        }

        if (!preg_match('/GET \/?\S*callback\?([^ ]+)/', $request, $m)) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'Callback OAuth malformado ou URL de redirecionamento inesperada.',
            );
        }

        /** @var array<string, mixed> $params */
        $params = [];
        parse_str($m[1], $params);

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
