<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\VendaCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Command\Venda\ImprimirCommand;
use ContaAzulCli\Command\Venda\ItensCommand;
use ContaAzulCli\Command\Venda\ProximoNumeroCommand;
use ContaAzulCli\Command\Venda\VendedoresCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;

/**
 * Wiring-only: confirms each command name maps to the right class. The
 * generic Support classes' own behavior is already covered in
 * tests/Integration/Command/Support/, and the custom Venda commands have
 * their own tests, so this does not repeat that here.
 */
final class VendaCommandModuleTest extends CommandTestCase
{
  public function testRegistersEverySaleCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new VendaCommandModule(
        $this->vendasClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(9, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['venda list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['venda create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['venda get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['venda update']);
    self::assertInstanceOf(ImprimirCommand::class, $byName['venda imprimir']);
    self::assertInstanceOf(ItensCommand::class, $byName['venda itens']);
    self::assertInstanceOf(VendedoresCommand::class, $byName['venda vendedores']);
    self::assertInstanceOf(ProximoNumeroCommand::class, $byName['venda proximo-numero']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['venda excluir-lote']);
  }
}
