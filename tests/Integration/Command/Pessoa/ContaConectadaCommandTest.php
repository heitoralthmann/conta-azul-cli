<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\ContaConectadaCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class ContaConectadaCommandTest extends CommandTestCase
{
  public function testFetchesTheConnectedAccountCompany(): void {
    $output  = $this->newOutput();
    $command = new ContaConectadaCommand(
        $this->pessoasClient([$this->jsonResponse(['id' => 'empresa-1', 'nome' => 'Minha Empresa'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'empresa-1', 'nome' => 'Minha Empresa'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testAuthFailureIsRenderedAsAuthFailedEnvelope(): void {
    $output  = $this->newOutput();
    $command = new ContaConectadaCommand(
        $this->pessoasClient([$this->errorResponse(401), $this->errorResponse(401)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('auth_failed', $envelope['kind']);
  }
}
