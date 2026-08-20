<?php

declare(strict_types=1);

namespace ContaAzulCli\Bootstrap;

use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Output\FormatterRegistry;
use ContaAzulCli\Output\FormatterSelectorInterface;
use ContaAzulCli\Output\Logger;
use Symfony\Component\Console\Command\Command;

/** Immutable services and commands assembled for the console application. */
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
      private readonly FormatterRegistry $formatterRegistry,
      private readonly FormatterSelectorInterface $formatterSelector,
  ) {
  }

  /** Returns the logger used by API clients and the application shell. */
  public function logger(): Logger {
    return $this->logger;
  }

  /** Returns the formatters available to `--format`. */
  public function formatterRegistry(): FormatterRegistry {
    return $this->formatterRegistry;
  }

  /** Returns the per-invocation formatter holder shared by all renderers. */
  public function formatterSelector(): FormatterSelectorInterface {
    return $this->formatterSelector;
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
