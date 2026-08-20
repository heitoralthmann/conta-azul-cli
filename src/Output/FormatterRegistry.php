<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use InvalidArgumentException;

use function array_key_exists;
use function array_keys;
use function implode;

/**
 * Maps formatter names to implementations.
 *
 * The composition root registers formatters here. `--format` looks up names
 * from this map, so a new format is one class plus one entry in
 * {@see self::withDefaults()}.
 */
final class FormatterRegistry
{
  /** @var array<string, ResponseFormatterInterface> */
  private array $formatters = [];

  /**
   * Indexes formatters by {@see ResponseFormatterInterface::name()}.
   *
   * @param list<ResponseFormatterInterface> $formatters
   */
  public function __construct(
      array $formatters,
      private readonly string $defaultName = ToonFormatter::NAME,
  ) {
    if ($formatters === []) {
      throw new InvalidArgumentException('FormatterRegistry requires at least one formatter.');
    }

    foreach ($formatters as $formatter) {
      $name = $formatter->name();
      if (array_key_exists($name, $this->formatters)) {
        throw new InvalidArgumentException('Duplicate formatter name: ' . $name);
      }

      $this->formatters[$name] = $formatter;
    }

    if (! array_key_exists($this->defaultName, $this->formatters)) {
      throw new InvalidArgumentException(
          'Default formatter "' . $this->defaultName . '" is not registered.',
      );
    }
  }

  /** Built-in TOON (default) and JSON formatters. */
  public static function withDefaults(): self {
    return new self([new ToonFormatter(), new JsonFormatter()]);
  }

  /** Whether a formatter is registered under this machine name. */
  public function has(string $name): bool {
    return array_key_exists($name, $this->formatters);
  }

  /**
   * Returns the formatter registered under `$name`.
   *
   * @throws InvalidArgumentException When the name is not registered.
   */
  public function get(string $name): ResponseFormatterInterface {
    if (! $this->has($name)) {
      throw new InvalidArgumentException(
          'Unknown formatter "' . $name . '". Registered: ' . implode(', ', $this->names()),
      );
    }

    return $this->formatters[$name];
  }

  /** Returns the default formatter (TOON unless constructed otherwise). */
  public function default(): ResponseFormatterInterface {
    return $this->get($this->defaultName);
  }

  /**
   * Registered formatter names, in registration order.
   *
   * @return list<string>
   */
  public function names(): array {
    return array_keys($this->formatters);
  }
}
