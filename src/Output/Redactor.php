<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

/** Recursively replaces known credential fields before values are logged. */
final class Redactor
{
  private const SENSITIVE_KEYS = [
    'authorization',
    'client_secret',
    'access_token',
    'refresh_token',
    'ca_bootstrap_refresh_token',
    'password',
  ];


  /**
   * Redacts sensitive keys in a nested value tree.
   *
   * @param array<string|int, mixed> $data
   * @return array<string|int, mixed>
   */
  public function redact(array $data): array {
    $result = [];
    foreach ($data as $key => $value) {
      if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, TRUE)) {
        $result[$key] = '[REDACTED]';
      } elseif (is_array($value)) {
        $result[$key] = $this->redact($value);
      } else {
        $result[$key] = $value;
      }
    }

    return $result;
  }


  /** Redacts credential fields embedded in a JSON-like string. */
  public function redactString(string $value): string {
    foreach (self::SENSITIVE_KEYS as $key) {
      $value = (string) preg_replace(
        '/(\"' . preg_quote($key, '/') . '\"\s*:\s*\")[^\"]*(\")/',
        '$1[REDACTED]$2',
        $value,
      );
    }

    return $value;
  }


}
