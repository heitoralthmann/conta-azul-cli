<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Financeiro;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

final class AlteracoesCommandTest extends CommandTestCase
{
  public function testListsChangesWithExplicitIntervalAndNoWarning(): void {
    $output  = $this->newOutput();
    $command = new AlteracoesCommand(
        $this->financeiroClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
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
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(new DateTimeImmutable('2026-08-16')),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $warning = self::decodeEnvelope($output->stderr());
    self::assertSame('warning', $warning['kind']);
    self::assertIsString($warning['message']);
    self::assertStringContainsString('2026-08-01T00:00:00', $warning['message']);
  }

  public function testForwardsPaginationOptionsAsQueryParameters(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new AlteracoesCommand(
        $this->financeiroClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        [
          '--data-fim'       => '2026-08-31T23:59:59',
          '--data-inicio'    => '2026-08-01T00:00:00',
          '--pagina'         => '3',
          '--tamanho-pagina' => '10',
        ],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01T00:00:00', $query['data_inicio'] ?? null);
    self::assertSame('2026-08-31T23:59:59', $query['data_fim'] ?? null);
    self::assertSame('3', $query['pagina'] ?? null);
    self::assertSame('10', $query['tamanho_pagina'] ?? null);
  }

  public function testSendsDefaultPaginationWhenTheFlagsAreOmitted(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new AlteracoesCommand(
        $this->financeiroClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $this->runCommand(
        $command,
        ['--data-inicio' => '2026-08-01T00:00:00', '--data-fim' => '2026-08-31T23:59:59'],
    );

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('1', $query['pagina'] ?? null);
    self::assertSame('50', $query['tamanho_pagina'] ?? null);
  }

  public function testUnsupportedPageSizeFailsBeforeCallingTheApi(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new AlteracoesCommand(
        $this->financeiroClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $tester = $this->runCommand(
        $command,
        [
          '--data-fim'       => '2026-08-31T23:59:59',
          '--data-inicio'    => '2026-08-01T00:00:00',
          '--tamanho-pagina' => '15',
        ],
    );

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertNull($captured);

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
  }
}
