<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\HttpErrorMapper;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Authenticated Symfony HTTP adapter with the API's retry and error policy.
 */
final class HttpApiTransport implements ApiTransportInterface
{
    private readonly string $correlationId;
    private readonly HttpErrorMapper $errorMapper;


    /**
     * @param SleeperInterface $sleeper Delay implementation, normally NativeSleeper.
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly AuthManager $authManager,
        private readonly Logger $logger,
        private readonly Redactor $redactor,
        private readonly HttpClientInterface $httpClient,
        private readonly SleeperInterface $sleeper,
        private readonly RetryPolicy $retryPolicy,
    ) {
        $this->correlationId = Uuid::uuid4()->toString();
        $this->errorMapper   = new HttpErrorMapper();
    }


    /**
     * {@inheritDoc}
     */
    public function request(string $method, string $path, array $options=[]): array {
        $url     = $this->config->getApiBaseUrl() . $path;
        $attempt = 0;

        while (TRUE) {
            $attempt++;
            $accessToken = $this->authManager->getValidAccessToken();
            $requestOptions = $this->buildRequestOptions($options, $accessToken);
            $this->logRequest($method, $url, $options);

            try {
                $response   = $this->httpClient->request($method, $url, $requestOptions);
                $statusCode = $response->getStatusCode();
            } catch (\Throwable $e) {
                if ($this->retryPolicy->shouldRetryTransport($method, $attempt)) {
                    $this->sleeper->sleep($this->retryPolicy->delay($attempt));
                    continue;
                }

                throw $this->errorMapper->mapTransportError($e, $this->correlationId);
            }

            if ($statusCode === 401 && $attempt === 1) {
                $this->authManager->refreshAfter401();
                continue;
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return $this->decodeSuccess($response, $statusCode);
            }

            if ($this->retryPolicy->shouldRetryResponse($method, $statusCode, $attempt)) {
                $this->sleeper->sleep($this->retryPolicy->delay($attempt, $this->extractRetryAfter($response)));
                continue;
            }

            throw $this->errorMapper->mapResponse($response, $method, $this->correlationId);
        }
    }


    /**
     * {@inheritDoc}
     */
    public function getCorrelationId(): string {
        return $this->correlationId;
    }


    /**
     * Adds authentication, correlation, and JSON headers to caller options.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function buildRequestOptions(array $options, string $accessToken): array {
        /** @var array<string, string> $existingHeaders */
        $existingHeaders = is_array($options['headers'] ?? NULL) ? $options['headers'] : [];
        $headers = array_merge(
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

        return array_merge($options, ['headers' => $headers]);
    }


    /**
     * Logs a request while redacting query values when logging is enabled.
     *
     * @param array<string, mixed> $options
     */
    private function logRequest(string $method, string $url, array $options): void {
        if (!$this->logger->isEnabled()) {
            return;
        }

        /** @var array<mixed> $queryForLog */
        $queryForLog = is_array($options['query'] ?? NULL) ? $options['query'] : [];
        $this->logger->log(
          'debug',
          'API request',
          [
                'method' => $method,
                'url'    => $url,
                'query'  => $this->redactor->redact($queryForLog),
            ],
          $this->correlationId,
        );
    }


    /**
     * Decodes a successful response and preserves the 204 empty-payload contract.
     *
     * @return array<mixed>
     */
    private function decodeSuccess(ResponseInterface $response, int $statusCode): array {
        if ($statusCode === 204) {
            return [];
        }

        $data = $response->toArray();
        if ($this->logger->isEnabled()) {
            $this->logger->log('debug', 'API response', ['status' => $statusCode], $this->correlationId);
        }

        return $data;
    }


    /**
     * Reads a numeric Retry-After response header, if available.
     */
    private function extractRetryAfter(ResponseInterface $response): ?float {
        try {
            $headers = $response->getHeaders(FALSE);
            $values  = $headers['retry-after'] ?? [];
            if ($values !== []) {
                return (float) $values[0];
            }
        } catch (\Throwable) {
        }

        return NULL;
    }


}
