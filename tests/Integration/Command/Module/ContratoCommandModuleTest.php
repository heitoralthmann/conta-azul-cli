<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Contrato\ListCommand;
use ContaAzulCli\Command\Contrato\ProximoNumeroCommand;
use ContaAzulCli\Command\Module\ContratoCommandModule;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;

/**
 * Wiring-only: confirms each command name maps to the right class. The
 * generic `ResourceJsonCommand`'s own behavior is already covered in
 * tests/Integration/Command/Support/, so this does not repeat that here.
 */
final class ContratoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryContractCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new ContratoCommandModule(
        $this->contratosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(6, $byName);
    self::assertInstanceOf(ListCommand::class, $byName['contrato list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['contrato create']);
    self::assertInstanceOf(ProximoNumeroCommand::class, $byName['contrato proximo-numero']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato get']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato delete']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato encerrar']);
  }
}
