<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\NotaFiscal;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\NotaFiscal\ListCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class ListCommandTest extends CommandTestCase
{
  public function testListsWithExplicitDatesAndNoWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->notasFiscaisClient([$this->jsonResponse(['itens' => [], 'paginacao' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-inicial' => '2026-08-01', '--data-final' => '2026-08-31'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [], 'paginacao' => []], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingDatesFallsBackToCurrentMonthWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->notasFiscaisClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(new DateTimeImmutable('2026-08-16')),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], json_decode($output->stdout(), true));

    $warning = json_decode($output->stderr(), true);
    self::assertSame('warning', $warning['kind']);
    self::assertStringContainsString('2026-08-01', $warning['message']);
    self::assertStringContainsString('2026-08-31', $warning['message']);
  }

  /**
   * `GET /v1/notas-fiscais` answers 400 above 100, so the CLI must refuse the size
   * itself instead of spending a round trip to be told.
   *
   * The client is built with **no** queued responses on purpose: if the
   * command ever stops passing the cap, it reaches the transport and the
   * test fails there rather than silently passing.
   */
  public function testRejectsAPageSizeTheEndpointCapsWithoutCallingTheApi(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->notasFiscaisClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand($command, [
      '--data-final'     => '2026-08-31',
      '--data-inicial'   => '2026-08-01',
      '--tamanho-pagina' => '200',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $error = json_decode($output->stderr(), true);
    self::assertSame('client_error', $error['kind']);
    self::assertSame(
        'Tamanho de página inválido: 200. Valores aceitos: 10, 20, 50, 100',
        $error['message'],
    );
  }
}
