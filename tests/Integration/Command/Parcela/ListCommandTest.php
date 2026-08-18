<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Parcela;

use ContaAzulCli\Command\Parcela\ListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class ListCommandTest extends CommandTestCase
{
  public function testListsTheInstallmentsOfAnEventAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->financeiroClient([$this->jsonResponse([['id' => 'parcela-1', 'indice' => 1]])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id-evento' => 'evento-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame([['id' => 'parcela-1', 'indice' => 1]], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testUnknownEventIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->financeiroClient([$this->errorResponse(404)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['id-evento' => 'inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
