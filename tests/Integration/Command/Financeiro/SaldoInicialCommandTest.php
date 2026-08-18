<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Financeiro;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Financeiro\SaldoInicialCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class SaldoInicialCommandTest extends CommandTestCase
{
  public function testListsInitialBalancesWithExplicitIntervalAndNoWarning(): void {
    $output  = $this->newOutput();
    $command = new SaldoInicialCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-inicio' => '2026-08-01T00:00:00', '--data-fim' => '2026-08-31T23:59:59'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIntervalFallsBackToCurrentMonthWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new SaldoInicialCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(new DateTimeImmutable('2026-08-16')),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $warning = json_decode($output->stderr(), true);
    self::assertSame('warning', $warning['kind']);
    self::assertStringContainsString('2026-08-01T00:00:00', $warning['message']);
  }
}
