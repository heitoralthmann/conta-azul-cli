<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Captura\EnviarCommand;
use ContaAzulCli\Command\Captura\StatusCommand;
use ContaAzulCli\Command\Module\CapturaCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Wiring-only: confirms each command name maps to the right command class.
 * ResourceIdCommand's own request/response/error behavior is already
 * covered in tests/Integration/Command/Support/, so this does not repeat
 * that here.
 */
final class CapturaCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryCaptureCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new CapturaCommandModule(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(5, $byName);
    self::assertInstanceOf(EnviarCommand::class, $byName['captura enviar']);
    self::assertInstanceOf(StatusCommand::class, $byName['captura status']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['captura get']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['captura aceitar']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['captura recusar']);
  }

  /**
   * Option -> query mapping, which the client tests cannot catch: the client
   * forwards whatever key it is handed.
   *
   * `--ids a,b` must leave as a repeated `ids` parameter. Comma-joined is
   * what the published OpenAPI declares (`explode: false`) and what this
   * command sent until 2026-08-19; production answers `400` to it, so every
   * multi-id call failed while single-id calls kept working — the two
   * encodings coincide for one id.
   */
  public function testStatusSendsEachIdAsItsOwnRepeatedQueryParameter(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new StatusCommand(
        $this->capturaClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1, doc-2, doc-3']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertNotNull($captured);

    $query = (string) parse_url($captured['url'], PHP_URL_QUERY);
    self::assertStringContainsString('ids=doc-1&ids=doc-2&ids=doc-3', $query);
  }

  /** Negative assertion, so the encoding the API rejects cannot come back. */
  public function testStatusNeverSendsTheCommaJoinedOrIndexedIdsTheApiRejects(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new StatusCommand(
        $this->capturaClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $this->runCommand($command, ['--ids' => 'doc-1,doc-2']);

    self::assertNotNull($captured);
    $query = (string) parse_url($captured['url'], PHP_URL_QUERY);
    self::assertStringNotContainsString('ids=doc-1%2Cdoc-2', $query);
    self::assertStringNotContainsString('ids=doc-1,doc-2', $query);
    self::assertStringNotContainsString('ids%5B', $query);
    self::assertStringNotContainsString('ids[', $query);
  }

  public function testStatusForwardsPaginationOptionsAsQueryParameters(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new StatusCommand(
        $this->capturaClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $this->runCommand($command, ['--ids' => 'doc-1', '--pagina' => '3', '--tamanho-pagina' => '15']);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('3', $query['pagina'] ?? null);
    self::assertSame('15', $query['tamanho_pagina'] ?? null);
  }
}
