<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Categoria;

use ContaAzulCli\Command\Categoria\ConfiguracaoPadraoCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class ConfiguracaoPadraoCommandTest extends CommandTestCase
{
  public function testFetchesTheDefaultCategoryConfiguration(): void {
    $output  = $this->newOutput();
    $command = new ConfiguracaoPadraoCommand(
        $this->financeiroClient([$this->jsonResponse([['tipo_operacao' => 'FRETES_RECEBIDOS']])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame([['tipo_operacao' => 'FRETES_RECEBIDOS']], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testApiErrorIsRenderedAsAnEnvelopeOnStderr(): void {
    $output  = $this->newOutput();
    $command = new ConfiguracaoPadraoCommand(
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
