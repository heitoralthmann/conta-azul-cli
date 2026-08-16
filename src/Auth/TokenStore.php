<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;

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
    if (!is_dir($dir)) {
      mkdir($dir, 0700, TRUE);
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
  public function load(): ?TokenData {
    if (!file_exists($this->tokenPath)) {
      return NULL;
    }

    try {
      $content = file_get_contents($this->tokenPath);
      if ($content === FALSE || $content === '') {
        return NULL;
      }

      $decoded = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($decoded)) {
        return NULL;
      }
      /** @var array<string, mixed> $decoded */
      return TokenData::fromArray($decoded);
    } catch (\Throwable) {
      return NULL;
    }
  }


  /** Deletes the token file when it exists. */
  public function delete(): void {
    if (file_exists($this->tokenPath)) {
      unlink($this->tokenPath);
    }
  }


  /** Returns the path used by this store, for lock coordination. */
  public function getPath(): string {
    return $this->tokenPath;
  }


}
