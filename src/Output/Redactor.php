<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use function in_array;
use function is_array;
use function preg_quote;
use function preg_replace;
use function str_ends_with;
use function strtolower;

/** Recursively replaces known credential fields before values are logged. */
final class Redactor
{
  private const array SENSITIVE_KEYS = [
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
   *
   * @return array<string|int, mixed>
   */
  public function redact(array $data): array {
    $result = [];
    foreach ($data as $key => $value) {
      if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
        $result[$key] = '[REDACTED]';
      } elseif (is_array($value)) {
        $result[$key] = $this->redact($value);
      } else {
        $result[$key] = $value;
      }
    }

    return $result;
  }

  /**
   * Whether a configuration variable names a credential.
   *
   * `ca config show` asks this so the sensitive-key list stays defined in one
   * place. The match allows a prefix because environment variables carry one
   * (`CA_CLIENT_SECRET`) while the API payload fields {@see self::redact()}
   * handles do not (`client_secret`).
   */
  public function isSensitive(string $key): bool {
    $normalized = strtolower($key);
    foreach (self::SENSITIVE_KEYS as $sensitive) {
      if ($normalized === $sensitive || str_ends_with($normalized, '_' . $sensitive)) {
        return true;
      }
    }

    return false;
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
