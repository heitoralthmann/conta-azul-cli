<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\FormatterRegistry;
use ContaAzulCli\Output\JsonFormatter;
use ContaAzulCli\Output\ToonFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies formatter lookup, defaults, and registration guards.
 */
final class FormatterRegistryTest extends TestCase
{
  /** Built-in formatters are TOON (default) and JSON. */
  public function testWithDefaultsRegistersToonAndJson(): void {
    $registry = FormatterRegistry::withDefaults();

    self::assertSame(['toon', 'json'], $registry->names());
    self::assertSame('toon', $registry->default()->name());
    self::assertTrue($registry->has('json'));
    self::assertFalse($registry->has('yaml'));
  }

  /** `get()` returns the formatter registered under that name. */
  public function testGetReturnsRegisteredFormatter(): void {
    $json = new JsonFormatter();

    self::assertSame($json, (new FormatterRegistry([new ToonFormatter(), $json]))->get('json'));
  }

  /** Unknown names are a programmer error, not a CLI `kind`. */
  public function testGetUnknownNameThrows(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Unknown formatter "yaml"');

    FormatterRegistry::withDefaults()->get('yaml');
  }

  /** An empty registry cannot serve a default. */
  public function testEmptyRegistryThrows(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('at least one formatter');

    new FormatterRegistry([]);
  }

  /** Duplicate names would make `--format` ambiguous. */
  public function testDuplicateNameThrows(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate formatter name: toon');

    new FormatterRegistry([new ToonFormatter(), new ToonFormatter()]);
  }

  /** The default name must be one of the registered formatters. */
  public function testMissingDefaultThrows(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Default formatter "toon" is not registered');

    new FormatterRegistry([new JsonFormatter()]);
  }
}
