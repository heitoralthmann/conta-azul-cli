<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\LegadoCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class LegadoCommandTest extends CommandTestCase
{
  public function testFetchesThePersonByLegacyIdAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new LegadoCommand(
        $this->pessoasClient([$this->jsonResponse(['id' => 'p-1', 'idLegado' => '12345'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => '12345']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'p-1', 'idLegado' => '12345'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testUnknownLegacyIdIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new LegadoCommand(
        $this->pessoasClient([$this->errorResponse(404)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
