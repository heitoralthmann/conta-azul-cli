<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\ContaAPagar;

use ContaAzulCli\Command\ContaAPagar\CreateCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class CreateCommandTest extends CommandTestCase
{
  public function testCreatesAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([$this->jsonResponse(['id' => 'cap-1'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['--json' => '{"descricao":"Aluguel"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'cap-1'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingJsonOptionFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }
}
