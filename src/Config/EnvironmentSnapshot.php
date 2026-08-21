<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function is_string;

/**
 * The process environment as it stood before any `.env` file was loaded.
 *
 * This is what lets `ca config show` name the origin of every value. Once
 * Dotenv has run with `usePutenv(true)`, `getenv()` no longer distinguishes a
 * variable exported by the operator from one that came out of the file, so the
 * distinction has to be captured up front — the very first thing
 * {@see \ContaAzulCli\ContaAzulApplication} does.
 *
 * `$_SERVER` is the source rather than `getenv()` because Symfony's Dotenv
 * populates both, and `$_SERVER` is already a plain array to copy. The CLI SAPI
 * fills it with the real environment regardless of `variables_order`.
 */
final readonly class EnvironmentSnapshot
{
  /** @param array<string, string> $variables */
  private function __construct(private array $variables) {
  }

  /** Copies the current process environment. */
  public static function capture(): self {
    $variables = [];
    foreach ($_SERVER as $name => $value) {
      if (! is_string($name) || ! is_string($value)) {
        continue;
      }

      $variables[$name] = $value;
    }

    return new self($variables);
  }

  /**
   * Builds a snapshot from explicit values, for deterministic tests.
   *
   * @param array<string, string> $variables
   */
  public static function fromArray(array $variables): self {
    return new self($variables);
  }

  /**
   * Whether the real environment defined this variable with a usable value.
   *
   * Empty counts as absent, matching {@see EnvironmentConfigurationLoader},
   * which falls back to the default for an empty variable.
   */
  public function has(string $name): bool {
    return ($this->variables[$name] ?? '') !== '';
  }

  /** Returns the captured value, or null when it was absent or empty. */
  public function get(string $name): string|null {
    $value = $this->variables[$name] ?? '';

    return $value === '' ? null : $value;
  }
}
