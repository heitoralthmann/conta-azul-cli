<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Command\Venda\VendedoresCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class VendedoresCommandTest extends CommandTestCase
{
  public function testListsSellers(): void {
    $output  = $this->newOutput();
    $command = new VendedoresCommand(
        $this->vendasClient([$this->jsonResponse([['id' => 'v-1', 'nome' => 'João da Silva']])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame([['id' => 'v-1', 'nome' => 'João da Silva']], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testApiErrorIsRenderedAsAnEnvelopeOnStderr(): void {
    $output  = $this->newOutput();
    $command = new VendedoresCommand(
        $this->vendasClient([$this->errorResponse(400)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
