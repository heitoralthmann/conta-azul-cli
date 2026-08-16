<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use Symfony\Component\Console\Command\Command;

/** Executes one command operation and translates known failures to CLI output. */
final class CommandExecutor
{
  /** Creates an executor backed by the shared error renderer. */
  public function __construct(private readonly ErrorEnvelope $errorEnvelope) {
  }

  /**
   * Runs an operation and returns the command exit code.
   *
   * @param callable(): void $operation Operation that may throw a CliException
   */
  public function execute(callable $operation): int {
    try {
      $operation();

      return Command::SUCCESS;
    } catch (CliException $e) {
      $this->errorEnvelope->renderToStderr($e);

      return Command::FAILURE;
    }
  }
}
