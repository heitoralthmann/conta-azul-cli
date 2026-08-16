<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\ContaFinanceira;

use ContaAzulCli\Command\ContaFinanceira\SaldoCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class SaldoCommandTest extends CommandTestCase
{
  public function testFetchesTheBalanceForTheGivenAccount(): void {
    $output  = $this->newOutput();
    $command = new SaldoCommand(
        $this->financeiroClient([$this->jsonResponse(['saldo' => 1500.75])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['--id' => 'cf-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['saldo' => 1500.75], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIdFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new SaldoCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }
}
