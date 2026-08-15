<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpErrorMapper
{
    public function mapResponse(ResponseInterface $response, string $method, string $correlationId): CliException
    {
        $status = $response->getStatusCode();
        $isWriteMethod = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        try {
            $body = $this->normalizeBody($response->getContent(false));
        } catch (\Throwable) {
            $body = '';
        }

        return match (true) {
            $status === 401 => new CliException(
                ErrorKind::AuthFailed,
                false,
                $this->authFailedMessage($body),
                $status,
                null,
                $correlationId,
            ),
            $status === 429 => new CliException(
                ErrorKind::RateLimited,
                true,
                'Limite de requisições atingido. Aguarde e tente novamente.',
                $status,
                null,
                $correlationId,
            ),
            $status >= 400 && $status < 500 => new CliException(
                ErrorKind::ClientError,
                false,
                "Requisição inválida (HTTP {$status}): {$body}",
                $status,
                null,
                $correlationId,
            ),
            $status >= 500 && $isWriteMethod => new CliException(
                ErrorKind::Ambiguous,
                false,
                "Erro do servidor em escrita (HTTP {$status}). A operação pode ter sido aplicada. Reconcilie via: ca financeiro alteracoes",
                $status,
                null,
                $correlationId,
            ),
            $status >= 500 => new CliException(
                ErrorKind::ServerError,
                true,
                "Erro do servidor (HTTP {$status}): {$body}",
                $status,
                null,
                $correlationId,
            ),
            default => new CliException(
                ErrorKind::ServerError,
                false,
                "Resposta inesperada (HTTP {$status})",
                $status,
                null,
                $correlationId,
            ),
        };
    }

    /**
     * The API answers 401 with the reason the credentials were rejected — for
     * instance that the authorization screen needs the ERP user rather than a
     * personal login. Dropping it costs the operator the one hint that resolves
     * the failure.
     */
    private function authFailedMessage(string $body): string
    {
        $base = 'Autenticação falhou. Execute: ca auth login';

        return $body === '' ? $base : "{$base} Resposta da API: {$body}";
    }

    /**
     * Conta Azul pretty-prints error bodies, so they arrive wrapped across lines
     * and deeply indented. The envelope is a single compact JSON object, so the
     * body is flattened rather than embedded verbatim.
     */
    private function normalizeBody(string $body): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($body));

        return trim($collapsed ?? $body);
    }

    public function mapTransportError(\Throwable $e, string $correlationId): CliException
    {
        return new CliException(
            ErrorKind::Transient,
            true,
            "Erro de transporte: {$e->getMessage()}",
            null,
            null,
            $correlationId,
            $e,
        );
    }
}
