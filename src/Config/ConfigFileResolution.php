<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function array_map;

/** Outcome of walking the environment-file search path once. */
final readonly class ConfigFileResolution
{
  /**
   * Pairs the winning file, if any, with the full audit trail.
   *
   * @param string|null               $path       File that won, or null when none exists.
   * @param list<ConfigFileCandidate> $candidates Every entry considered, in order.
   */
  public function __construct(
      private string|null $path,
      private array $candidates,
  ) {
  }

  /** Returns the environment file to load, or null when there is none. */
  public function path(): string|null {
    return $this->path;
  }

  /**
   * Returns every search-path entry, in precedence order.
   *
   * @return list<ConfigFileCandidate>
   */
  public function candidates(): array {
    return $this->candidates;
  }

  /**
   * Renders the whole audit trail for the response formatter.
   *
   * @return list<array{caminho: string|null, origem: string, status: string}>
   */
  public function toArray(): array {
    return array_map(
        static fn (ConfigFileCandidate $candidate): array => $candidate->toArray(),
        $this->candidates,
    );
  }
}
