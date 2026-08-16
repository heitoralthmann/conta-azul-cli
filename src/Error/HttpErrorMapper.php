<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

use Symfony\Contracts\HttpClient\ResponseInterface;

/** Maps HTTP responses and transport failures to normalized CLI exceptions. */
final class HttpErrorMapper
{


    /**
     * Maps an unsuccessful API response according to status and HTTP method.
     *
     * @throws \Throwable Only failures while reading the response are handled internally.
     */

    public function mapResponse(ResponseInterface $response, string $method, string $correlationId): CliException {
        $status = $response->getStatusCode();
        $isWriteMethod = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], TRUE);

        try {
            $body = $this->normalizeBody($response->getContent(FALSE));
        } catch (\Throwable) {
            $body = '';
        }

        return match (TRUE) {
            $status === 401 => new CliException(
              ErrorKind::AuthFailed,
              FALSE,
                $this->authFailedMessage($body),
                $status,
                NULL,
                $correlationId,
            ),
            $status === 429 => new CliException(
              ErrorKind::RateLimited,
              TRUE,
                'Limite de requisições atingido. Aguarde e tente novamente.',
                $status,
                NULL,
                $correlationId,
            ),
            $status >= 400 && $status < 500 => new CliException(
              ErrorKind::ClientError,
              FALSE,
                "Requisição inválida (HTTP {$status}): {$body}",
                $status,
                NULL,
                $correlationId,
            ),
            $status >= 500 && $isWriteMethod => new CliException(
              ErrorKind::Ambiguous,
              FALSE,
                "Erro do servidor em escrita (HTTP {$status}). A operação pode ter sido aplicada. Reconcilie via: ca financeiro alteracoes",
                $status,
                NULL,
                $correlationId,
            ),
            $status >= 500 => new CliException(
              ErrorKind::ServerError,
              TRUE,
                "Erro do servidor (HTTP {$status}): {$body}",
                $status,
                NULL,
                $correlationId,
            ),
            default => new CliException(
              ErrorKind::ServerError,
              FALSE,
              "Resposta inesperada (HTTP {$status})",
              $status,
              NULL,
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
    private function authFailedMessage(string $body): string {
        $base = 'Autenticação falhou. Execute: ca auth login';

        return $body === '' ? $base : "{$base} Resposta da API: {$body}";
    }


    /**
     * Conta Azul pretty-prints error bodies, so they arrive wrapped across lines
     * and deeply indented. The envelope is a single compact JSON object, so the
     * body is flattened rather than embedded verbatim.
     */
    private function normalizeBody(string $body): string {
        $collapsed = preg_replace('/\s+/', ' ', trim($body));

        return trim($collapsed ?? $body);
    }


    /** Converts a transport exception into a retryable transient CLI error. */
    public function mapTransportError(\Throwable $e, string $correlationId): CliException {
        return new CliException(
          ErrorKind::Transient,
          TRUE,
          "Erro de transporte: {$e->getMessage()}",
          NULL,
          NULL,
          $correlationId,
          $e,
        );
    }


}
