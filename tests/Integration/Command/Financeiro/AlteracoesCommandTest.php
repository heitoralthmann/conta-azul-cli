<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Financeiro;

use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;

final class AlteracoesCommandTest extends CommandTestCase
{
  public function testListsChangesWithExplicitIntervalAndNoWarning(): void {
    $output  = $this->newOutput();
    $command = new AlteracoesCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-inicio' => '2026-08-01T00:00:00', '--data-fim' => '2026-08-31T23:59:59'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIntervalFallsBackToCurrentMonthWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new AlteracoesCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new WarningEnvelope($output),
        new PeriodoPadrao(new DateTimeImmutable('2026-08-16')),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $warning = self::decodeEnvelope($output->stderr());
    self::assertSame('warning', $warning['kind']);
    self::assertStringContainsString('2026-08-01T00:00:00', $warning['message']);
  }
}
