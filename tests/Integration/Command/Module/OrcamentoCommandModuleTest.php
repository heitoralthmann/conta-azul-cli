<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\OrcamentoCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
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
final class OrcamentoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryBudgetCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new OrcamentoCommandModule(
        $this->orcamentosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(4, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['orcamento list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['orcamento create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['orcamento get']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['orcamento excluir-lote']);
  }
}
