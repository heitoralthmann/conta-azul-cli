<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Contrato;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Contrato\ListCommand;
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
        $this->contratosClient([$this->jsonResponse(['itens_totais' => 0, 'items' => []])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        ['--data-inicio' => '2026-08-01', '--data-fim' => '2026-08-31'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens_totais' => 0, 'items' => []], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingDatesFallsBackToCurrentMonthWithAWarning(): void {
    $output  = $this->newOutput();
    $command = new ListCommand(
        $this->contratosClient([$this->jsonResponse(['itens_totais' => 0, 'items' => []])]),
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
    self::assertStringContainsString('2026-08-01', $warning['message']);
  }
}
