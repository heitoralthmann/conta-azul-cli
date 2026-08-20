<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function rtrim;

/**
 * Writes already-formatted payloads through Symfony's output contract.
 *
 * Success goes to stdout. Diagnostics go to stderr whenever the supplied
 * output exposes a separate error stream.
 */
final class ConsoleWriter
{
  private readonly OutputInterface $output;

  /**
   * Creates a stream writer.
   *
   * The default is suitable for the standalone CLI. Tests and embedders can
   * pass a BufferedOutput or another Symfony output implementation.
   */
  public function __construct(OutputInterface|null $output = null) {
    $this->output = $output ?? new ConsoleOutput();
  }

  /** Writes a successful command payload to stdout. */
  public function writeSuccess(string $payload): void {
    $this->write($this->output, $payload);
  }

  /** Writes a diagnostic payload (error or warning) to stderr. */
  public function writeDiagnostic(string $payload): void {
    $this->write($this->errorOutput(), $payload);
  }

  /**
   * Returns the configured error stream, or the main stream when no separate
   * error stream is available (for example, with BufferedOutput in a test).
   */
  private function errorOutput(): OutputInterface {
    return $this->output instanceof ConsoleOutputInterface ? $this->output->getErrorOutput() : $this->output;
  }

  /**
   * Writes one formatted document without Symfony markup processing.
   *
   * OUTPUT_RAW is important here: string values may contain characters such
   * as angle brackets that Symfony's markup formatter would otherwise
   * interpret when output is decorated.
   */
  private function write(OutputInterface $output, string $payload): void {
    $output->write(
        rtrim($payload, "\n") . "\n",
        false,
        OutputInterface::OUTPUT_RAW,
    );
  }
}
