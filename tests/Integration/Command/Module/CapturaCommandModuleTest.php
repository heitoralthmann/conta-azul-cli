<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Captura\EnviarCommand;
use ContaAzulCli\Command\Captura\StatusCommand;
use ContaAzulCli\Command\Module\CapturaCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;

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
        new JsonRenderer($output),
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
}
