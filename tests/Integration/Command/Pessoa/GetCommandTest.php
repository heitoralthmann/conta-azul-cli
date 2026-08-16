<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\GetCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class GetCommandTest extends CommandTestCase
{
  public function testFetchesThePersonAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->pessoasClient([$this->jsonResponse(['id' => 'p-1', 'nome' => 'Maria'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'p-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'p-1', 'nome' => 'Maria'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testUnknownPersonIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->pessoasClient([$this->errorResponse(404)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
