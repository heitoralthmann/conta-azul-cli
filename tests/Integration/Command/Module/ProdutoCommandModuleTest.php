<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\ProdutoCommandModule;
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
final class ProdutoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryProductCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new ProdutoCommandModule(
        $this->produtosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(11, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['produto create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['produto get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['produto update']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['produto delete']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto categorias']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto cest']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto ncm']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto unidades-medida']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto ecommerce-categorias']);
    self::assertInstanceOf(ResourceListCommand::class, $byName['produto ecommerce-marcas']);
  }

  /**
   * `produto list --codigo` has to reach the API as `sku`.
   *
   * This mapping is not cosmetic: `GET /v1/produtos` answers 200 and ignores
   * every query parameter it does not recognize, so sending the wrong name
   * silently returns the whole catalog instead of failing. The CLI shipped
   * `codigo` (and `ids`/`categoria_id`, which no name satisfies) until they
   * were exercised against production on 2026-08-19.
   */
  public function testListMapsCodigoOptionToTheSkuQueryParameter(): void {
    $response = new MockResponse('{"totalItems":0,"items":[]}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new ProdutoCommandModule(
        $this->produtosClient([$response]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $this->runCommand($byName['produto list'], ['--codigo' => 'CAFE-01']);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);
    self::assertSame('CAFE-01', $query['sku'] ?? null);
    self::assertArrayNotHasKey('codigo', $query);
  }

  /**
   * `--ids` and `--categoria-id` were removed because `GET /v1/produtos`
   * ignores them under every name tried against production, which made them
   * return the full catalog while looking like a filter.
   */
  public function testListNoLongerOffersTheFiltersTheApiIgnores(): void {
    $output = $this->newOutput();
    $module = new ProdutoCommandModule(
        $this->produtosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $definition = $byName['produto list']->getDefinition();

    self::assertTrue($definition->hasOption('busca'));
    self::assertTrue($definition->hasOption('codigo'));
    self::assertTrue($definition->hasOption('status'));
    self::assertFalse($definition->hasOption('ids'));
    self::assertFalse($definition->hasOption('categoria-id'));
  }
}
