<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonFormatter;
use JsonException;
use PHPUnit\Framework\TestCase;

use const NAN;

/**
 * Verifies compact JSON encoding used by `--raw` / `--format=json`.
 */
final class JsonFormatterTest extends TestCase
{
  private JsonFormatter $formatter;

  protected function setUp(): void {
    $this->formatter = new JsonFormatter();
  }

  /** The `--format` name is the stable machine identifier `json`. */
  public function testNameIsJson(): void {
    self::assertSame('json', $this->formatter->name());
  }

  /** Success payloads stay one compact JSON line without extra whitespace. */
  public function testEncodesCompactJson(): void {
    self::assertSame('{"key":"value","num":42}', $this->formatter->format(['key' => 'value', 'num' => 42]));
  }

  /** Unicode is left unescaped so agents read the original characters. */
  public function testUnicodeIsNotEscaped(): void {
    self::assertStringContainsString('Olá, mundo!', $this->formatter->format(['msg' => 'Olá, mundo!']));
  }

  /** Forward slashes are not escaped, matching the previous JSON renderer. */
  public function testForwardSlashesAreNotEscaped(): void {
    $encoded = $this->formatter->format(['url' => 'https://example.com/path']);

    self::assertStringContainsString('https://example.com/path', $encoded);
    self::assertStringNotContainsString('https:\/\/', $encoded);
  }

  /** Compact JSON has no spaces around colons or commas. */
  public function testOutputHasNoExtraWhitespace(): void {
    $encoded = $this->formatter->format(['a' => 1, 'b' => 2]);

    self::assertStringNotContainsString(': ', $encoded);
    self::assertStringNotContainsString(', ', $encoded);
  }

  /** Encoding failures surface as JsonException, same as the old renderer. */
  public function testInvalidPayloadThrowsJsonException(): void {
    $this->expectException(JsonException::class);

    $this->formatter->format(NAN);
  }
}
