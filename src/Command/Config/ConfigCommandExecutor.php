<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Config;

use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;

/**
 * Runs one configuration operation and normalizes its failures.
 *
 * The config commands are the only family that speaks `ConfigException`: they
 * run before — and usually instead of — a successful bootstrap, so the layer
 * they touch predates the CLI's error contract. Translating in one place keeps
 * four commands free of identical catch blocks and guarantees they all answer
 * with the same envelope shape as every other command.
 */
final class ConfigCommandExecutor
{
  private readonly CommandExecutor $commandExecutor;

  /** Wraps the shared executor with configuration-specific error mapping. */
  public function __construct(
      ErrorEnvelope $errorEnvelope,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
  }

  /**
   * Runs an operation and returns the command exit code.
   *
   * @param callable(): void $operation Operation that may throw a ConfigException
   */
  public function execute(callable $operation): int {
    return $this->commandExecutor->execute(
        static function () use ($operation): void {
          try {
            $operation();
          } catch (ConfigException $e) {
            throw new CliException(ErrorKind::ClientError, false, $e->getMessage(), previous: $e);
          }
        },
    );
  }
}
