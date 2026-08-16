<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Backwards-compatible success-payload renderer.
 *
 * This adapter keeps existing command constructors stable while delegating
 * serialization and stream handling to JsonConsoleOutput.
 */
final class JsonRenderer
{
  private readonly JsonConsoleOutput $output;

  /**
   * Creates a success renderer.
   *
   * @param OutputInterface|null $output Symfony output used for stdout.
   */
  public function __construct(OutputInterface|null $output = null)
  {
    $this->output = new JsonConsoleOutput($output);
  }

  /**
   * Renders a successful command result as one compact JSON line.
   *
   * @throws JsonException If the result cannot be encoded as JSON.
   */
  public function render(mixed $data): void
  {
    $this->output->renderSuccess($data);
  }
}
