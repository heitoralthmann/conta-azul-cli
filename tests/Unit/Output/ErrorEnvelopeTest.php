<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonFormatter;
use ContaAzulCli\Output\MutableFormatterSelector;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function rtrim;

/**
 * Verifies error envelopes follow the selected response formatter.
 */
final class ErrorEnvelopeTest extends TestCase
{
  /** The default formatter is TOON and keeps the stable envelope fields. */
  public function testDefaultsToToon(): void {
    $output = new BufferedOutput();

    (new ErrorEnvelope($output))->renderToStderr(
        new CliException(ErrorKind::ClientError, false, 'Dados inválidos', 422, null, 'corr-1'),
    );

    $envelope = Toon::decode(rtrim($output->fetch(), "\n"));
    self::assertIsArray($envelope);
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(422, $envelope['http_status']);
    self::assertSame('corr-1', $envelope['correlation_id']);
    self::assertSame('Dados inválidos', $envelope['message']);
    self::assertNull($envelope['protocol_id']);
    self::assertFalse($envelope['retryable']);
  }

  /** A JSON selector emits the compact envelope scripts used to parse. */
  public function testFollowsJsonSelector(): void {
    $output   = new BufferedOutput();
    $selector = new MutableFormatterSelector(new JsonFormatter());

    (new ErrorEnvelope($output, $selector))->renderToStderr(
        new CliException(ErrorKind::ClientError, false, 'Dados inválidos', 422, null, 'corr-1'),
    );

    self::assertSame(
        '{"correlation_id":"corr-1","http_status":422,"kind":"client_error","message":"Dados inválidos",'
            . "\"protocol_id\":null,\"retryable\":false}\n",
        $output->fetch(),
    );
  }
}
