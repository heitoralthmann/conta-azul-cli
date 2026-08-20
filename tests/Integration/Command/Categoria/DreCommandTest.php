<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Categoria;

use ContaAzulCli\Command\Categoria\DreCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class DreCommandTest extends CommandTestCase
{
  public function testListsDreCategories(): void {
    $output  = $this->newOutput();
    $command = new DreCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => [['id' => 'dre-1']]])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [['id' => 'dre-1']]], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testApiErrorIsRenderedAsAnEnvelopeOnStderr(): void {
    $output  = $this->newOutput();
    $command = new DreCommand(
        $this->financeiroClient([$this->errorResponse(400)]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }
}
