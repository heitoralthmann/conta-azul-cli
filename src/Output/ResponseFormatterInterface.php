<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

/**
 * Encodes a command payload into one response format.
 *
 * Formatters are composed by the output boundary; they never write streams
 * and they do not inherit from one another. Adding a format means
 * implementing this interface and registering the class in
 * {@see FormatterRegistry::withDefaults()}.
 */
interface ResponseFormatterInterface
{
  /** Stable machine name used by `--format` (for example `toon` or `json`). */
  public function name(): string;

  /** Serializes a JSON-shaped value into this format's text representation. */
  public function format(mixed $data): string;
}
