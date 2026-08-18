<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/**
 * Exposes the shared transport operations retained by legacy API clients.
 *
 * This is a forwarding seam, not a second transport implementation: endpoint
 * clients remain composed around ApiClientSupport while keeping the methods
 * previously inherited from BaseClient available to callers.
 */
trait ApiClientOperations
{
  private readonly ApiClientSupport $support;

  /**
   * Returns the correlation identifier attached to this client's requests.
   */
  public function getCorrelationId(): string {
    return $this->support->getCorrelationId();
  }

  /**
   * Sends an authenticated request through the shared transport.
   *
   * @param array<string, mixed> $options
   *
   * @return array<mixed>
   */
  public function request(string $method, string $path, array $options = []): array {
    return $this->support->request($method, $path, $options);
  }

  /**
   * Sends an authenticated request whose response is a bare scalar or null.
   *
   * @param array<string, mixed> $options
   */
  public function requestScalar(string $method, string $path, array $options = []): mixed {
    return $this->support->requestScalar($method, $path, $options);
  }

  /**
   * Sends an authenticated request whose response is not JSON.
   *
   * @param array<string, mixed> $options
   *
   * @return array{content: string, contentType: string}
   */
  public function requestBinary(string $method, string $path, array $options = []): array {
    return $this->support->requestBinary($method, $path, $options);
  }

  /**
   * Polls an asynchronous protocol identifier.
   *
   * @return array<mixed>
   */
  public function pollProtocol(string $protocolId, int $timeoutSeconds = 60): array {
    return $this->support->pollProtocol($protocolId, $timeoutSeconds);
  }

  /**
   * Returns an accepted response immediately or waits for its protocol.
   *
   * @param array<mixed> $response
   *
   * @return array<mixed>
   */
  public function handleAsyncResponse(array $response, int $pollTimeout = 60, bool $noWait = false): array {
    return $this->support->handleAsyncResponse($response, $pollTimeout, $noWait);
  }
}
