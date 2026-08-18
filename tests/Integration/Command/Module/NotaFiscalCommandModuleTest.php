<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\NotaFiscalCommandModule;
use ContaAzulCli\Command\NotaFiscal\GetCommand as NotaFiscalGetCommand;
use ContaAzulCli\Command\NotaFiscal\ListCommand as NotaFiscalListCommand;
use ContaAzulCli\Command\NotaFiscalServico\ListCommand as NotaFiscalServicoListCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
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
final class NotaFiscalCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryInvoiceCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new NotaFiscalCommandModule(
        $this->notasFiscaisClient([]),
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

    self::assertCount(4, $byName);
    self::assertInstanceOf(NotaFiscalListCommand::class, $byName['nota-fiscal list']);
    self::assertInstanceOf(NotaFiscalGetCommand::class, $byName['nota-fiscal get']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['nota-fiscal vincular-mdfe']);
    self::assertInstanceOf(NotaFiscalServicoListCommand::class, $byName['nota-fiscal-servico list']);
  }
}
