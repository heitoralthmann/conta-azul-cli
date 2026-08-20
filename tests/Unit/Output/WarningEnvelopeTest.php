<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonFormatter;
use ContaAzulCli\Output\MutableFormatterSelector;
use ContaAzulCli\Output\WarningEnvelope;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function rtrim;

/**
 * Verifies warning envelopes follow the selected response formatter.
 */
final class WarningEnvelopeTest extends TestCase
{
  /** Warnings default to TOON on the configured stream. */
  public function testDefaultsToToon(): void {
    $output = new BufferedOutput();

    (new WarningEnvelope($output))->renderToStderr('Atenção');

    self::assertSame(
        ['kind' => 'warning', 'message' => 'Atenção'],
        Toon::decode(rtrim($output->fetch(), "\n")),
    );
  }

  /** A JSON selector keeps the previous compact warning envelope. */
  public function testFollowsJsonSelector(): void {
    $output   = new BufferedOutput();
    $selector = new MutableFormatterSelector(new JsonFormatter());

    (new WarningEnvelope($output, $selector))->renderToStderr('Atenção');

    self::assertSame("{\"kind\":\"warning\",\"message\":\"Atenção\"}\n", $output->fetch());
  }
}
