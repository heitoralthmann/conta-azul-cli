<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Support;

use function array_map;
use function array_values;

/**
 * Reads fields out of a decoded command payload.
 *
 * The TOON and JSON decoders both hand back `mixed`. Keeping the casts here
 * lets the assertions in each test read as plain comparisons instead of
 * carrying type gymnastics.
 */
trait DecodedPayloads
{
  /**
   * Reads one column out of a decoded list-of-rows payload.
   *
   * @return list<mixed>
   */
  protected static function column(mixed $value, string $key): array {
    return array_map(
        static fn (mixed $row): mixed => self::field($row, $key),
        self::rows($value),
    );
  }

  /** Reads one field out of a decoded payload row. */
  protected static function field(mixed $row, string $key): mixed {
    $data = (array) $row;

    return $data[$key] ?? null;
  }

  /** @return list<mixed> */
  protected static function rows(mixed $value): array {
    return array_values((array) $value);
  }
}
