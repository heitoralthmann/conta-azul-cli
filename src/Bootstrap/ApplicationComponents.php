<?php

declare(strict_types=1);

namespace ContaAzulCli\Bootstrap;

use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Output\Logger;
use Symfony\Component\Console\Command\Command;

/**
 * Immutable services and commands assembled for the console application.
 *
 * The formatter registry and selector deliberately do not travel through here:
 * the application shell owns them, because it needs them registered on the
 * always-available commands before this factory output exists at all.
 */
final class ApplicationComponents
{
  /**
   * Creates an immutable collection of feature modules and shared logging.
   *
   * @param list<CommandModuleInterface> $modules
   */
  public function __construct(
      private readonly Logger $logger,
      private readonly array $modules,
  ) {
  }

  /** Returns the logger used by API clients and the application shell. */
  public function logger(): Logger {
    return $this->logger;
  }

  /**
   * Flattens feature modules into the list expected by Symfony Console.
   *
   * @return list<Command>
   */
  public function commands(): array {
    $commands = [];
    foreach ($this->modules as $module) {
      foreach ($module->commands() as $command) {
        $commands[] = $command;
      }
    }

    return $commands;
  }
}
