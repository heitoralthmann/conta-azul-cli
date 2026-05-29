<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Error\HttpErrorMapper;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BaseClient
{
    private readonly string $correlationId;
    private readonly HttpErrorMapper $errorMapper;

    /** @var list<float> */
    private const GET_RETRY_BACKOFF = [0.5, 2.0, 8.0];
    private const POLL_INITIAL_SLEEP = 1.0;
    private const POLL_MAX_SLEEP     = 8.0;

    public function __construct(
        private readonly Configuration $config,
        private readonly AuthManager $authManager,
        private readonly Logger $logger,
        private readonly Redactor $redactor,
        private readonly HttpClientInterface $httpClient,
    ) {
        $this->correlationId = Uuid::uuid4()->toString();
        $this->errorMapper   = new HttpErrorMapper();
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $url     = $this->config->getApiBaseUrl() . $path;
        $isWrite = in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        $retryable429 = [429];
        $retryableGet = [429, 502, 503, 504];

        $maxAttempts = 3;
        $attempt     = 0;

        while (true) {
            $attempt++;
            $accessToken = $this->authManager->getValidAccessToken();

            /** @var array<string, string> $existingHeaders */
            $existingHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
            $headers         = array_merge(
                $existingHeaders,
                [
                    'Authorization'    => "Bearer {$accessToken}",
                    'X-Correlation-Id' => $this->correlationId,
                    'Accept'           => 'application/json',
                ],
            );

            if (isset($options['json'])) {
                $headers['Content-Type'] = 'application/json';
            }

            $requestOptions = array_merge($options, ['headers' => $headers]);

            if ($this->logger->isEnabled()) {
                /** @var array<mixed> $queryForLog */
                $queryForLog = is_array($options['query'] ?? null) ? $options['query'] : [];
                $this->logger->log('debug', 'API request', [
                    'method' => $method,
                    'url'    => $url,
                    'query'  => $this->redactor->redact($queryForLog),
                ], $this->correlationId);
            }

            try {
                $response   = $this->httpClient->request($method, $url, $requestOptions);
                $statusCode = $response->getStatusCode();
            } catch (\Throwable $e) {
                if (!$isWrite && $attempt < $maxAttempts) {
                    $this->sleep(self::GET_RETRY_BACKOFF[$attempt - 1]);
                    continue;
                }
                throw $this->errorMapper->mapTransportError($e, $this->correlationId);
            }

            if ($statusCode === 401 && $attempt === 1) {
                $this->authManager->refreshAfter401();
                continue;
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                if ($statusCode === 204) {
                    return [];
                }
                $data = $response->toArray();
                if ($this->logger->isEnabled()) {
                    $this->logger->log('debug', 'API response', ['status' => $statusCode], $this->correlationId);
                }

                return $data;
            }

            $retryStatuses = $isWrite ? $retryable429 : $retryableGet;
            if (in_array($statusCode, $retryStatuses, true) && $attempt < $maxAttempts) {
                $retryAfter = $this->extractRetryAfter($response);
                $backoff     = $retryAfter ?? self::GET_RETRY_BACKOFF[$attempt - 1];
                $this->sleep($backoff);
                continue;
            }

            throw $this->errorMapper->mapResponse($response, $method, $this->correlationId);
        }
    }

    /**
     * @return array<mixed>
     */
    public function pollProtocol(string $protocolId, int $timeoutSeconds = 60): array
    {
        $start        = time();
        $sleepSeconds = self::POLL_INITIAL_SLEEP;

        while (true) {
            try {
                $data = $this->request('GET', "/v1/protocolo/{$protocolId}");
            } catch (CliException $e) {
                throw new CliException(
                    ErrorKind::PollDropKnownId,
                    true,
                    "Polling interrompido por erro de transporte. protocol_id: {$protocolId}. Retome com: ca protocolo get {$protocolId}",
                    null,
                    $protocolId,
                    $this->correlationId,
                    $e,
                );
            }

            $rawStatus = $data['status'] ?? '';
            $status    = is_string($rawStatus) ? $rawStatus : '';

            if ($status === 'SUCCESS') {
                /** @var array<mixed> $payload */
                $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

                return $payload;
            }

            if ($status === 'ERROR') {
                throw new CliException(
                    ErrorKind::ServerError,
                    false,
                    "Operação falhou no servidor. protocol_id: {$protocolId}",
                    null,
                    $protocolId,
                    $this->correlationId,
                );
            }

            if ((time() - $start) >= $timeoutSeconds) {
                throw new CliException(
                    ErrorKind::PollTimeoutKnownId,
                    false,
                    "Timeout aguardando resultado. Consulte manualmente: ca protocolo get {$protocolId}",
                    null,
                    $protocolId,
                    $this->correlationId,
                );
            }

            $this->sleep($sleepSeconds);
            $sleepSeconds = min($sleepSeconds * 2.0, self::POLL_MAX_SLEEP);
        }
    }

    /**
     * @param array<mixed> $response
     * @return array<mixed>
     */
    public function handleAsyncResponse(array $response, int $pollTimeout = 60, bool $noWait = false): array
    {
        $rawProtocolId = $response['protocolId'] ?? '';
        $protocolId    = is_string($rawProtocolId) ? $rawProtocolId : '';

        if ($protocolId === '' || $noWait) {
            return $response;
        }

        return $this->pollProtocol($protocolId, $pollTimeout);
    }

    private function extractRetryAfter(ResponseInterface $response): ?float
    {
        try {
            $headers = $response->getHeaders(false);
            $values  = $headers['retry-after'] ?? [];
            if ($values !== []) {
                return (float) $values[0];
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function sleep(float $seconds): void
    {
        $jitter = $seconds * 0.2;
        $actual = $seconds + (mt_rand() / mt_getrandmax() * 2.0 - 1.0) * $jitter;
        usleep((int) ($actual * 1_000_000));
    }
}
