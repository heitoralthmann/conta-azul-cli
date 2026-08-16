<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use Throwable;

use function chmod;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;

/** Stores OAuth credentials in a local JSON file with restrictive permissions. */
final class TokenStore implements TokenRepositoryInterface
{
  private string $tokenPath;

  /** Creates a store using the configured token file path. */
  public function __construct(Configuration $config) {
    $this->tokenPath = $config->getTokenPath();
  }

  /** Persists a token atomically enough for concurrent CLI invocations. */
  public function save(TokenData $token): void {
    $dir = dirname($this->tokenPath);
    if (! is_dir($dir)) {
      mkdir($dir, 0700, true);
    }

    $json = json_encode(
        $token->toArray(),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
    );
    file_put_contents($this->tokenPath, $json, LOCK_EX);
    // No-op on Windows, where PHP's chmod only toggles the read-only flag:
    // the file carries credentials, so on that platform it relies on the
    // user profile directory's own ACLs.
    chmod($this->tokenPath, 0600);
  }

  /** Loads a token, treating missing, empty, and corrupt files as absent. */
  public function load(): TokenData|null {
    if (! file_exists($this->tokenPath)) {
      return null;
    }

    try {
      $content = file_get_contents($this->tokenPath);
      if ($content === false || $content === '') {
        return null;
      }

      $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
      if (! is_array($decoded)) {
        return null;
      }

      /** @var array<string, mixed> $token */
      $token = $decoded;

      return TokenData::fromArray($token);
    } catch (Throwable) {
      return null;
    }
  }

  /** Deletes the token file when it exists. */
  public function delete(): void {
    if (! file_exists($this->tokenPath)) {
      return;
    }

    unlink($this->tokenPath);
  }

  /** Returns the path used by this store, for lock coordination. */
  public function getPath(): string {
    return $this->tokenPath;
  }
}
