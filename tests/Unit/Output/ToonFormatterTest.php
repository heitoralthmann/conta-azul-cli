<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\ToonFormatter;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

use function rtrim;

/**
 * Verifies TOON encoding used as the CLI default response format.
 */
final class ToonFormatterTest extends TestCase
{
  private ToonFormatter $formatter;

  protected function setUp(): void {
    $this->formatter = new ToonFormatter();
  }

  /** The `--format` name is the stable machine identifier `toon`. */
  public function testNameIsToon(): void {
    self::assertSame('toon', $this->formatter->name());
  }

  /** Objects round-trip through encode/decode as associative arrays. */
  public function testRoundTripsAnObject(): void {
    $data = ['key' => 'value', 'num' => 42];

    self::assertSame($data, Toon::decode($this->formatter->format($data)));
  }

  /** Uniform lists of objects round-trip, including unicode field values. */
  public function testRoundTripsAListOfObjects(): void {
    $data = [
      ['id' => 1, 'nome' => 'Olá'],
      ['id' => 2, 'nome' => 'Ada'],
    ];

    self::assertSame($data, Toon::decode($this->formatter->format($data)));
  }

  /** Scalar payloads such as `proximo-numero` stay numbers. */
  public function testRoundTripsAScalar(): void {
    self::assertSame(4512645, Toon::decode($this->formatter->format(4512645)));
  }

  /** Default output is TOON, not JSON. */
  public function testDoesNotEmitJsonObjectBraces(): void {
    $encoded = $this->formatter->format(['key' => 'value']);

    self::assertStringStartsWith('key:', rtrim($encoded));
    self::assertStringNotContainsString('{', $encoded);
  }
}
