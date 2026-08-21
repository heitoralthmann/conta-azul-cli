<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Command\Config\InitCommand;
use ContaAzulCli\Command\Config\PathCommand;
use ContaAzulCli\Command\Config\SetCommand;
use ContaAzulCli\Command\Config\ShowCommand;
use ContaAzulCli\Command\Module\ConfigCommandModule;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Config\EnvironmentSnapshot;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;

/**
 * Wiring-only: confirms each command name maps to the right command class.
 * Behavior lives in tests/Integration/Command/Config/.
 */
final class ConfigCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryConfigCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new ConfigCommandModule(
        new ConfigFileLocator('/nao/usado', null),
        new EnvFileWriter(),
        new Redactor(),
        EnvironmentSnapshot::fromArray([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(4, $byName);
    self::assertInstanceOf(PathCommand::class, $byName['config path']);
    self::assertInstanceOf(ShowCommand::class, $byName['config show']);
    self::assertInstanceOf(InitCommand::class, $byName['config init']);
    self::assertInstanceOf(SetCommand::class, $byName['config set']);
  }
}
