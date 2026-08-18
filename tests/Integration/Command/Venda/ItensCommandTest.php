<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Venda\ItensCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class ItensCommandTest extends CommandTestCase
{
  public function testListsTheSalesItems(): void {
    $output  = $this->newOutput();
    $command = new ItensCommand(
        $this->vendasClient([$this->jsonResponse(['itens' => [], 'itens_totais' => 0])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['id-venda' => 'venda-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [], 'itens_totais' => 0], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testInvalidPageSizeIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new ItensCommand(
        $this->vendasClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['id-venda' => 'venda-1', '--tamanho-pagina' => '7']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
