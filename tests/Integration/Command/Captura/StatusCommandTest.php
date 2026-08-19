<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Captura;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Captura\StatusCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class StatusCommandTest extends CommandTestCase
{
  public function testQueriesStatusForTheGivenIds(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1, doc-2']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIdsIsRenderedAsClientErrorWithoutCallingTheApi(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, []);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }

  public function testInvalidPageSizeIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1', '--tamanho-pagina' => '7']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
