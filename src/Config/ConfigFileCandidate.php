<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

/** One entry of the environment-file search path, with the verdict on it. */
final readonly class ConfigFileCandidate
{
  /**
   * Records where a candidate came from and what happened to it.
   *
   * @param string           $origin Operator-facing label for the search-path entry.
   * @param string|null      $path   Absolute path, or null when none could be formed.
   * @param ConfigFileStatus $status Why the candidate was or was not used.
   */
  public function __construct(
      public string $origin,
      public string|null $path,
      public ConfigFileStatus $status,
  ) {
  }

  /**
   * Renders the candidate for the response formatter.
   *
   * @return array{caminho: string|null, origem: string, status: string}
   */
  public function toArray(): array {
    return [
      'caminho' => $this->path,
      'origem'  => $this->origin,
      'status'  => $this->status->value,
    ];
  }
}
