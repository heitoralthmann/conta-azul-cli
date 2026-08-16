<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\PatchCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class PatchCommandTest extends CommandTestCase
{
  public function testPartiallyUpdatesThePersonAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new PatchCommand(
        $this->pessoasClient([$this->jsonResponse(['id' => 'p-1', 'email' => 'maria@example.test'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'p-1', '--json' => '{"email":"maria@example.test"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'p-1', 'email' => 'maria@example.test'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingJsonOptionFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new PatchCommand(
        $this->pessoasClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'p-1']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
