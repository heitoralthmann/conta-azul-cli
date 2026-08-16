<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Error\CliException;

/**
 * Executes authenticated requests against the Conta Azul API.
 *
 * Implementations own the HTTP boundary and its retry/error behavior. API
 * resource clients depend on this contract instead of a concrete HTTP client.
 */
interface ApiTransportInterface
{
  /**
   * Sends a request and returns its decoded JSON payload.
   *
   * @param array<string, mixed> $options Symfony HttpClient request options.
   *
   * @return array<mixed>
   *
   * @throws CliException when the request cannot succeed.
   */
  public function request(string $method, string $path, array $options = []): array;

  /**
   * Returns the identifier attached to every request in this transport.
   */
  public function getCorrelationId(): string;
}
