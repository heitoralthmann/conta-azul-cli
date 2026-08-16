<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Encapsulates safe retry rules and backoff for API requests. */
final class RetryPolicy
{
  /** @var list<float> */
  private const BACKOFF = [0.5, 2.0, 8.0];

  private const MAX_ATTEMPTS = 3;


  /**
   * Reports whether a failed HTTP response may be replayed.
   */
  public function shouldRetryResponse(string $method, int $statusCode, int $attempt): bool {
    if ($attempt >= self::MAX_ATTEMPTS) {
      return FALSE;
    }

    $retryable = $this->isWrite($method) ? [429] : [429, 502, 503, 504];

    return in_array($statusCode, $retryable, TRUE);
  }


  /**
   * Reports whether a transport exception may be retried.
   */
  public function shouldRetryTransport(string $method, int $attempt): bool {
    return !$this->isWrite($method) && $attempt < self::MAX_ATTEMPTS;
  }


  /**
   * Selects Retry-After when supplied, otherwise the documented backoff.
   */
  public function delay(int $attempt, ?float $retryAfter=NULL): float {
    if ($retryAfter !== NULL) {
      return $retryAfter;
    }

    return self::BACKOFF[$attempt - 1] ?? self::BACKOFF[array_key_last(self::BACKOFF)];
  }


  /**
   * Identifies methods whose request may have been applied and must not be replayed.
   */
  private function isWrite(string $method): bool {
    return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], TRUE);
  }


}
