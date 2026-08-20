<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\BatchCommand;
use ContaAzulCli\Command\Pessoa\BatchOperation;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;

/** Covers the three bulk operations PessoaCommandModule wires this class to. */
final class BatchCommandTest extends CommandTestCase
{
  /** @return array<string, array{BatchOperation, string}> */
  public static function operations(): array {
    return [
      'ativar'   => [BatchOperation::Activate, 'pessoa ativar'],
      'excluir'  => [BatchOperation::Delete, 'pessoa excluir'],
      'inativar' => [BatchOperation::Deactivate, 'pessoa inativar'],
    ];
  }

  #[DataProvider('operations')]
  public function testExecutesTheSelectedBulkOperation(BatchOperation $operation, string $name): void {
    $output  = $this->newOutput();
    $command = new BatchCommand(
        $this->pessoasClient([$this->jsonResponse(['sucesso' => ['p-1', 'p-2']])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        $name,
        $operation,
    );

    $tester = $this->runCommand($command, ['--json' => '{"uuids":["p-1","p-2"]}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['sucesso' => ['p-1', 'p-2']], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testMissingJsonOptionFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new BatchCommand(
        $this->pessoasClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        'pessoa ativar',
        BatchOperation::Activate,
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }
}
