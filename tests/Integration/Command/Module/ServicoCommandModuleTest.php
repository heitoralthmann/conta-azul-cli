<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\ServicoCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Wiring-only: confirms each command name maps to the right generic Support
 * class. The generic classes' own request/response/error behavior is
 * already covered in tests/Integration/Command/Support/, so this does not
 * repeat that here.
 */
final class ServicoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryServiceCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new ServicoCommandModule(
        $this->servicosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(5, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['servico list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['servico create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['servico get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['servico update']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['servico delete']);
  }

  /**
   * `servico list --busca` has to reach the API as `busca_textual`.
   *
   * Products and people call the same idea `busca`, but `GET /v1/servicos`
   * only answers to `busca_textual`. Since the endpoint returns 200 and
   * silently drops unknown query parameters, the wrong name returned the
   * whole catalog instead of failing — verified against production on
   * 2026-08-19.
   */
  public function testListMapsBuscaOptionToTheBuscaTextualQueryParameter(): void {
    $response = new MockResponse('{"itens":[],"paginacao":{}}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new ServicoCommandModule(
        $this->servicosClient([$response]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $this->runCommand($byName['servico list'], ['--busca' => 'PINTURA']);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);
    self::assertSame('PINTURA', $query['busca_textual'] ?? null);
    self::assertArrayNotHasKey('busca', $query);
  }

  /**
   * `busca` is the only filter `GET /v1/servicos` honors. `codigo`, `ids`
   * and `status` were removed after production ignored them under every
   * name tried, which made them return the full catalog while looking like
   * filters.
   */
  public function testListOnlyOffersTheSingleFilterTheApiHonors(): void {
    $output = $this->newOutput();
    $module = new ServicoCommandModule(
        $this->servicosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $definition = $byName['servico list']->getDefinition();

    self::assertTrue($definition->hasOption('busca'));
    self::assertFalse($definition->hasOption('codigo'));
    self::assertFalse($definition->hasOption('ids'));
    self::assertFalse($definition->hasOption('status'));
  }
}
