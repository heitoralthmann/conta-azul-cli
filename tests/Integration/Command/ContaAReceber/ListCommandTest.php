<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\ContaAReceber;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\ContaAReceber\ListCommand;
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
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-vencimento-de' => '2026-08-01', '--data-vencimento-ate' => '2026-08-31'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testMissingDatesFallsBackToCurrentMonthWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
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
    self::assertStringContainsString('2026-08-01', $warning['message']);
    self::assertStringContainsString('2026-08-31', $warning['message']);
  }
}
