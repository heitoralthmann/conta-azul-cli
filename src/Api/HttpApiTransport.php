<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\HttpErrorMapper;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function array_merge;
use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Authenticated Symfony HTTP adapter with the API's retry and error policy.
 */
final class HttpApiTransport implements ApiTransportInterface
{
  private readonly string $correlationId;
  private readonly HttpErrorMapper $errorMapper;

  /**
   * @param Configuration       $config      API endpoint configuration.
   * @param AuthManager         $authManager Provider for valid and refreshed tokens.
   * @param Logger              $logger      Structured request/response logger.
   * @param Redactor            $redactor    Removes sensitive query values from logs.
   * @param HttpClientInterface $httpClient  Symfony HTTP adapter.
   * @param SleeperInterface    $sleeper     Delay implementation, normally NativeSleeper.
   * @param RetryPolicy         $retryPolicy Safe replay rules for each HTTP method.
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
  public function request(string $method, string $path, array $options = []): array {
    $response = $this->sendWithRetry($method, $path, $options);

    return $response->getStatusCode() === 204 ? [] : $this->decodeArray($response);
  }

  /**
   * {@inheritDoc}
   */
  public function requestScalar(string $method, string $path, array $options = []): mixed {
    $response = $this->sendWithRetry($method, $path, $options);

    return $response->getStatusCode() === 204 ? null : $this->decodeScalar($response);
  }

  /**
   * {@inheritDoc}
   */
  public function requestBinary(string $method, string $path, array $options = []): array {
    $response = $this->sendWithRetry($method, $path, $options);

    return [
      'content'     => $response->getContent(),
      'contentType' => $this->extractContentType($response),
    ];
  }

  public function getCorrelationId(): string {
    return $this->correlationId;
  }

  /**
   * Sends a request, applying auth refresh and retry policy, and returns the
   * first successful (2xx) response.
   *
   * @param array<string, mixed> $options
   *
   * @throws CliException when the request cannot succeed.
   */
  private function sendWithRetry(string $method, string $path, array $options): ResponseInterface {
    $url     = $this->config->apiBaseUrl . $path;
    $attempt = 0;

    while (true) {
      $attempt++;
      $accessToken    = $this->authManager->getValidAccessToken();
      $requestOptions = $this->buildRequestOptions($options, $accessToken);
      $this->logRequest($method, $url, $options);

      try {
        $response   = $this->httpClient->request($method, $url, $requestOptions);
        $statusCode = $response->getStatusCode();
      } catch (Throwable $e) {
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
        if ($this->logger->isEnabled()) {
          $this->logger->log('debug', 'API response', ['status' => $statusCode], $this->correlationId);
        }

        return $response;
      }

      if ($this->retryPolicy->shouldRetryResponse($method, $statusCode, $attempt)) {
        $this->sleeper->sleep($this->retryPolicy->delay($attempt, $this->extractRetryAfter($response)));
        continue;
      }

      throw $this->errorMapper->mapResponse($response, $method, $this->correlationId);
    }
  }

  /**
   * Adds authentication, correlation, and JSON headers to caller options.
   *
   * @param array<string, mixed> $options
   *
   * @return array<string, mixed>
   */
  private function buildRequestOptions(array $options, string $accessToken): array {
    /** @var array<string, string> $existingHeaders */
    $existingHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
    $headers         = array_merge(
        $existingHeaders,
        [
          'Accept'           => 'application/json',
          'Authorization'    => 'Bearer ' . $accessToken,
          'X-Correlation-Id' => $this->correlationId,
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
    if (! $this->logger->isEnabled()) {
      return;
    }

    /** @var array<mixed> $queryForLog */
    $queryForLog = is_array($options['query'] ?? null) ? $options['query'] : [];
    $this->logger->log(
        'debug',
        'API request',
        [
          'method' => $method,
          'query'  => $this->redactor->redact($queryForLog),
          'url'    => $url,
        ],
        $this->correlationId,
    );
  }

  /** @return array<mixed> */
  private function decodeArray(ResponseInterface $response): array {
    return $response->toArray();
  }

  /**
   * Decodes a response body of any JSON shape, for endpoints whose payload
   * is a bare scalar or null rather than an object or array.
   */
  private function decodeScalar(ResponseInterface $response): mixed {
    return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Reads the Content-Type response header for a non-JSON payload.
   */
  private function extractContentType(ResponseInterface $response): string {
    try {
      $headers = $response->getHeaders(false);
      $values  = $headers['content-type'] ?? [];

      return $values[0] ?? 'application/octet-stream';
    } catch (Throwable) {
      return 'application/octet-stream';
    }
  }

  /**
   * Reads a numeric Retry-After response header, if available.
   */
  private function extractRetryAfter(ResponseInterface $response): float|null {
    try {
      $headers = $response->getHeaders(false);
      $values  = $headers['retry-after'] ?? [];
      if ($values !== []) {
        return (float) $values[0];
      }
    } catch (Throwable) {
    }

    return null;
  }
}
