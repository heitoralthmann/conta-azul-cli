<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\ContaFinanceira;

use ContaAzulCli\Command\ContaFinanceira\SaldoCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class SaldoCommandTest extends CommandTestCase
{
  public function testFetchesTheBalanceForTheGivenAccount(): void {
    $output  = $this->newOutput();
    $command = new SaldoCommand(
        $this->financeiroClient([$this->jsonResponse(['saldo' => 1500.75])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['--id' => 'cf-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['saldo' => 1500.75], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIdFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new SaldoCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
  }
}
