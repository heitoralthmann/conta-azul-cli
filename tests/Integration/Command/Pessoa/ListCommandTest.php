<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Pessoa\ListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends CommandTestCase
{
  public function testListsPeopleWithFilters(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->pessoasClient([$this->jsonResponse(['itens' => [['id' => 'p-1']]])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--busca' => 'Maria', '--com-endereco' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [['id' => 'p-1']]], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }
}
