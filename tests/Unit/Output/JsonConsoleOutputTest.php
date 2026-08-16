<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\JsonConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies JSON output boundary routing and formatting behavior.
 */
final class JsonConsoleOutputTest extends TestCase
{
  /**
   * Ensures successful payloads are emitted as compact raw JSON.
   */
  public function testRendersSuccessPayloadThroughSymfonyOutput(): void
  {
    $output = new BufferedOutput();

    (new JsonConsoleOutput($output))->renderSuccess(['value' => '<keep>']);

    self::assertSame("{\"value\":\"<keep>\"}\n", $output->fetch());
  }

  /**
   * Ensures warnings use the stable warning envelope on the configured sink.
   */
  public function testRendersWarningEnvelope(): void
  {
    $output = new BufferedOutput();

    (new JsonConsoleOutput($output))->renderWarning('Atenção');

    self::assertSame("{\"kind\":\"warning\",\"message\":\"Atenção\"}\n", $output->fetch());
  }

  /**
   * Ensures pre-normalized errors are emitted without changing their fields.
   */
  public function testRendersErrorEnvelope(): void
  {
    $output   = new BufferedOutput();
    $envelope = [
      'kind'           => 'client_error',
      'retryable'      => false,
      'http_status'    => 422,
      'protocol_id'    => null,
      'correlation_id' => 'corr-1',
      'message'        => 'Dados inválidos',
    ];

    (new JsonConsoleOutput($output))->renderError($envelope);

    self::assertSame(
        '{"kind":"client_error","retryable":false,"http_status":422,"protocol_id":null,'
            . "\"correlation_id\":\"corr-1\",\"message\":\"Dados inválidos\"}\n",
        $output->fetch(),
    );
  }
}
