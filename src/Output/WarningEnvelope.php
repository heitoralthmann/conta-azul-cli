<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders warnings as machine-readable JSON diagnostics on stderr.
 *
 * Stdout remains reserved for command payloads so pipeline consumers can
 * process successful results independently from warnings.
 */
final class WarningEnvelope
{
  private readonly JsonConsoleOutput $output;

  /**
   * Creates a warning-envelope renderer.
   *
   * @param OutputInterface|null $output Symfony output used for stderr.
   */
  public function __construct(OutputInterface|null $output = null)
  {
    $this->output = new JsonConsoleOutput($output);
  }

  /**
   * Renders a warning as one compact JSON line on stderr.
   *
   * @throws JsonException If the warning cannot be encoded as JSON.
   */
  public function renderToStderr(string $message): void
  {
    $this->output->renderWarning($message);
  }
}
