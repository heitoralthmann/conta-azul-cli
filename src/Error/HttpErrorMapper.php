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
            $body = $response->getContent(false);
        } catch (\Throwable) {
            $body = '';
        }

        return match (true) {
            $status === 401 => new CliException(
                ErrorKind::AuthFailed,
                false,
                'Autenticação falhou. Execute: ca auth login',
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
