<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class CallbackServer
{
    public function __construct(
        private readonly int $port = 9876,
        private readonly int $timeoutSeconds = 120,
    ) {}

    public function waitForCallback(string $expectedState): string
    {
        $server = @stream_socket_server("tcp://127.0.0.1:{$this->port}", $errno, $errstr);
        if ($server === false) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                "Não foi possível iniciar o servidor local na porta {$this->port}: {$errstr} ({$errno}). Verifique se a porta está livre.",
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

        parse_str($m[1], $params);

        if (($params['state'] ?? '') !== $expectedState) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'State OAuth inválido — possível ataque CSRF. Tente fazer login novamente.',
            );
        }

        if (empty($params['code'])) {
            $error = (string) ($params['error'] ?? 'desconhecido');
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                "Login negado ou erro retornado pelo servidor de autorização: {$error}",
            );
        }

        return (string) $params['code'];
    }
}
