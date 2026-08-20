<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use JsonException;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/** Encodes payloads as one compact JSON line. */
final class JsonFormatter implements ResponseFormatterInterface
{
  public const string NAME = 'json';

  /** Returns the `--format` name for this encoder. */
  public function name(): string {
    return self::NAME;
  }

  /**
   * Encodes `$data` as compact JSON with unescaped unicode and slashes.
   *
   * @throws JsonException If the payload cannot be encoded as JSON.
   */
  public function format(mixed $data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }
}
