<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use JsonException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Writes the CLI's machine-readable output through Symfony's output contract.
 *
 * Success payloads are sent to stdout, while diagnostics are sent to stderr
 * whenever the supplied output exposes a separate error stream.
 */
final class JsonConsoleOutput
{
  private readonly OutputInterface $output;

  /**
   * Creates a JSON output boundary.
   *
   * The default is suitable for the standalone CLI. Tests and embedders can
   * pass a BufferedOutput or another Symfony output implementation.
   */
  public function __construct(OutputInterface|null $output = null) {
    $this->output = $output ?? new ConsoleOutput();
  }

  /**
   * Writes a successful command payload to stdout.
   *
   * @throws JsonException If the payload cannot be encoded as JSON.
   */
  public function renderSuccess(mixed $data): void {
    $this->write($this->output, $data);
  }

  /**
   * Writes a warning envelope to stderr.
   *
   * @throws JsonException If the warning cannot be encoded as JSON.
   */
  public function renderWarning(string $message): void {
    $this->write(
        $this->errorOutput(),
        ['kind' => 'warning', 'message' => $message],
    );
  }

  /**
   * Writes an error envelope to stderr.
   *
   * @param array<string, mixed> $envelope A normalized error envelope.
   *
   * @throws JsonException If the envelope cannot be encoded as JSON.
   */
  public function renderError(array $envelope): void {
    $this->write($this->errorOutput(), $envelope);
  }

  /**
   * Returns the configured error stream, or the main stream when no separate
   * error stream is available (for example, with BufferedOutput in a test).
   */
  private function errorOutput(): OutputInterface {
    return $this->output instanceof ConsoleOutputInterface ? $this->output->getErrorOutput() : $this->output;
  }

  /**
   * Encodes and writes one compact JSON line without formatter processing.
   *
   * OUTPUT_RAW is important here: JSON string values may contain characters
   * such as angle brackets that Symfony's markup formatter would otherwise
   * interpret when output is decorated.
   *
   * @throws JsonException If the payload cannot be encoded as JSON.
   */
  private function write(OutputInterface $output, mixed $data): void {
    $output->write(
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n",
        false,
        OutputInterface::OUTPUT_RAW,
    );
  }
}
