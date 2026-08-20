<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\NotaFiscalServico;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\NotaFiscalServico\ListCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends CommandTestCase
{
  public function testListsWithExplicitDatesAndNoWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->notasFiscaisClient([$this->jsonResponse(['itens' => [], 'paginacao' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-competencia-de' => '2026-08-01', '--data-competencia-ate' => '2026-08-15'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [], 'paginacao' => []], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  /**
   * A API limita o intervalo de competência a 15 dias, então o default não
   * pode ser o mês corrente (mesma lógica dos demais comandos de listagem).
   */
  public function testMissingDatesFallsBackToA15DayWindowWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->notasFiscaisClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(new DateTimeImmutable('2026-08-16')),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], self::decodePayload($output->stdout()));

    $warning = self::decodeEnvelope($output->stderr());
    self::assertSame('warning', $warning['kind']);
    self::assertStringContainsString('2026-08-02', $warning['message']);
    self::assertStringContainsString('2026-08-16', $warning['message']);
  }

  /**
   * `GET /v1/notas-fiscais-servico` answers 400 above 100, so the CLI must refuse the size
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
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand($command, [
      '--data-competencia-ate' => '2026-08-31',
      '--data-competencia-de'  => '2026-08-01',
      '--tamanho-pagina'       => '200',
    ]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $error = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $error['kind']);
    self::assertSame(
        'Tamanho de página inválido: 200. Valores aceitos: 10, 20, 50, 100',
        $error['message'],
    );
  }
}
