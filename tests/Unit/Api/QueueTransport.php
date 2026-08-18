<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\ApiTransportInterface;

use function array_shift;

/** Supplies deterministic protocol responses to the polling service. */
final class QueueTransport implements ApiTransportInterface
{
  /** @param list<array<mixed>> $responses */
  public function __construct(private array $responses) {
  }

  /**
   * Returns the next protocol response.
   *
   * @param array<string, mixed> $options
   *
   * @return array<mixed>
   */
  public function request(string $method, string $path, array $options = []): array {
    return array_shift($this->responses) ?? [];
  }

  /** @param array<string, mixed> $options */
  public function requestScalar(string $method, string $path, array $options = []): mixed {
    return $this->request($method, $path, $options);
  }

  /** Returns a stable correlation id for generated polling errors. */
  public function getCorrelationId(): string {
    return 'correlation-test';
  }
}
