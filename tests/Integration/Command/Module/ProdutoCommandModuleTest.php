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
}
