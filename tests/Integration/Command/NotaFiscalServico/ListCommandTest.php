<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\NotaFiscalServico;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\NotaFiscalServico\ListCommand;
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
        ['--data-competencia-de' => '2026-08-01', '--data-competencia-ate' => '2026-08-15'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => [], 'paginacao' => []], json_decode($output->stdout(), true));
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
    self::assertStringContainsString('2026-08-02', $warning['message']);
    self::assertStringContainsString('2026-08-16', $warning['message']);
  }
}
