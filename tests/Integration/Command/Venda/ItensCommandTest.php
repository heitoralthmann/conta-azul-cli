<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Venda\ItensCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class ItensCommandTest extends CommandTestCase
{
  public function testListsTheSalesItems(): void {
    $output  = $this->newOutput();
    $command = new ItensCommand(
        $this->vendasClient([$this->jsonResponse(['itens' => [], 'itens_totais' => 0])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['id-venda' => 'venda-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [], 'itens_totais' => 0], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testInvalidPageSizeIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new ItensCommand(
        $this->vendasClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['id-venda' => 'venda-1', '--tamanho-pagina' => '7']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }
}
