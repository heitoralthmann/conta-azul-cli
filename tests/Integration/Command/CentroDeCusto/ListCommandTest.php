<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\CentroDeCusto;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\CentroDeCusto\ListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends CommandTestCase
{
  public function testListsCostCenters(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => [['id' => 'cc-1']]])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [['id' => 'cc-1']]], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }
}
