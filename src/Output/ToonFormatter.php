<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use HelgeSverre\Toon\Toon;

/** Encodes payloads as Token-Oriented Object Notation. */
final class ToonFormatter implements ResponseFormatterInterface
{
  public const string NAME = 'toon';

  /** Returns the `--format` name for this encoder. */
  public function name(): string {
    return self::NAME;
  }

  /** Encodes `$data` as TOON via the isolated vendor adapter. */
  public function format(mixed $data): string {
    return Toon::encode($data);
  }
}
