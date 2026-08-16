<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Pessoa;

use ContaAzulCli\Command\Pessoa\BatchCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;

use function json_decode;

/** Covers the three bulk operations PessoaCommandModule wires this class to. */
final class BatchCommandTest extends CommandTestCase
{
  /** @return array<string, array{'activate'|'deactivate'|'delete', string}> */
  public static function operations(): array {
    return [
      'ativar' => ['activate', 'pessoa ativar'],
      'inativar' => ['deactivate', 'pessoa inativar'],
      'excluir' => ['delete', 'pessoa excluir'],
    ];
  }

  /** @param 'activate'|'deactivate'|'delete' $operation */
  #[DataProvider('operations')]
  public function testExecutesTheSelectedBulkOperation(string $operation, string $name): void {
    $output  = $this->newOutput();
    $command = new BatchCommand(
        $this->pessoasClient([$this->jsonResponse(['sucesso' => ['p-1', 'p-2']])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        $name,
        $operation,
    );

    $tester = $this->runCommand($command, ['--json' => '{"uuids":["p-1","p-2"]}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['sucesso' => ['p-1', 'p-2']], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingJsonOptionFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new BatchCommand(
        $this->pessoasClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        'pessoa ativar',
        'activate',
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
