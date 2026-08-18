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
   * Sends a request and returns its decoded JSON payload without requiring
   * an array shape, for endpoints whose body is a bare scalar or null.
   *
   * @param array<string, mixed> $options Symfony HttpClient request options.
   *
   * @throws CliException when the request cannot succeed.
   */
  public function requestScalar(string $method, string $path, array $options = []): mixed;

  /**
   * Sends a request and returns its raw response body, for endpoints whose
   * payload is not JSON (e.g. XML or a binary file).
   *
   * @param array<string, mixed> $options Symfony HttpClient request options.
   *
   * @return array{content: string, contentType: string}
   *
   * @throws CliException when the request cannot succeed.
   */
  public function requestBinary(string $method, string $path, array $options = []): array;

  /**
   * Returns the identifier attached to every request in this transport.
   */
  public function getCorrelationId(): string;
}
