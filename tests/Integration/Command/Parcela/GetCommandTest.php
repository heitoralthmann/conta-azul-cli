<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Parcela;

use ContaAzulCli\Command\Parcela\GetCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class GetCommandTest extends CommandTestCase
{
  public function testFetchesTheInstallmentAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->financeiroClient([$this->jsonResponse(['id' => 'parcela-1', 'status' => 'aberta'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'parcela-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'parcela-1', 'status' => 'aberta'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testUnknownInstallmentIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->financeiroClient([$this->errorResponse(404)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
