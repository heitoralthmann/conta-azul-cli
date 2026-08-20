<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Command\Venda\VendedoresCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class VendedoresCommandTest extends CommandTestCase
{
  public function testListsSellers(): void {
    $output  = $this->newOutput();
    $command = new VendedoresCommand(
        $this->vendasClient([$this->jsonResponse([['id' => 'v-1', 'nome' => 'João da Silva']])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame([['id' => 'v-1', 'nome' => 'João da Silva']], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testApiErrorIsRenderedAsAnEnvelopeOnStderr(): void {
    $output  = $this->newOutput();
    $command = new VendedoresCommand(
        $this->vendasClient([$this->errorResponse(400)]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }
}
