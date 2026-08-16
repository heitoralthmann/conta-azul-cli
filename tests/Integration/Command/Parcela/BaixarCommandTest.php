<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Parcela;

use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class BaixarCommandTest extends CommandTestCase
{
  public function testRegistersPaymentAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([$this->jsonResponse(['id' => 'parcela-1', 'status' => 'baixada'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand(
        $command,
        ['id' => 'parcela-1', '--valor' => '100.50', '--data' => '2026-08-16'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'parcela-1', 'status' => 'baixada'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingValorFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'parcela-1', '--data' => '2026-08-16']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }

  public function testMissingDataFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'parcela-1', '--valor' => '100.50']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }

  public function testAmbiguousServerErrorOnWriteIsNotRetryable(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([$this->errorResponse(500)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand(
        $command,
        ['id' => 'parcela-1', '--valor' => '100.50', '--data' => '2026-08-16'],
    );

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = json_decode($output->stderr(), true);
    self::assertSame('ambiguous', $envelope['kind']);
    self::assertFalse($envelope['retryable']);
  }
}
