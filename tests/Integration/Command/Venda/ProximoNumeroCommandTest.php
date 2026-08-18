<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Command\Venda\ProximoNumeroCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class ProximoNumeroCommandTest extends CommandTestCase
{
  /**
   * O corpo da resposta é um inteiro solto, não um objeto — cobre o caminho
   * de decode escalar do transporte, não só a fiação do comando.
   */
  public function testFetchesTheNextSaleNumber(): void {
    $output  = $this->newOutput();
    $command = new ProximoNumeroCommand(
        $this->vendasClient([$this->jsonResponse(4512645)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(4512645, json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testAuthFailureIsRenderedAsAuthFailedEnvelope(): void {
    $output  = $this->newOutput();
    $command = new ProximoNumeroCommand(
        $this->vendasClient([$this->errorResponse(401), $this->errorResponse(401)]),
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
