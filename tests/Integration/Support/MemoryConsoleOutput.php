<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Support;

use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * Splits stdout and stderr into two in-memory streams.
 *
 * Commands build their ErrorEnvelope/ResponseRenderer with their own
 * OutputInterface at construction time, independent of the one Symfony
 * passes to Command::execute(). Mirroring ConsoleOutput's stream-splitting
 * behavior here — instead of a plain BufferedOutput — is what lets tests
 * assert stdout and stderr separately, the same way the real CLI process
 * separates them.
 */
final class MemoryConsoleOutput extends StreamOutput implements ConsoleOutputInterface
{
  private StreamOutput $stderrOutput;

  public function __construct() {
    parent::__construct(self::openMemoryStream(), self::VERBOSITY_NORMAL, false);

    $this->stderrOutput = new StreamOutput(self::openMemoryStream(), self::VERBOSITY_NORMAL, false);
  }

  public function getErrorOutput(): OutputInterface {
    return $this->stderrOutput;
  }

  public function setErrorOutput(OutputInterface $error): void {
    if (! $error instanceof StreamOutput) {
      throw new RuntimeException('MemoryConsoleOutput only accepts a StreamOutput error stream.');
    }

    $this->stderrOutput = $error;
  }

  public function section(): ConsoleSectionOutput {
    throw new RuntimeException('Console sections are not supported by MemoryConsoleOutput.');
  }

  /** Returns everything written to stdout so far. */
  public function stdout(): string {
    return self::readStream($this->getStream());
  }

  /** Returns everything written to stderr so far. */
  public function stderr(): string {
    return self::readStream($this->stderrOutput->getStream());
  }

  /** @return resource */
  private static function openMemoryStream() {
    $stream = fopen('php://memory', 'w+');
    if ($stream === false) {
      throw new RuntimeException('Unable to open an in-memory stream.');
    }

    return $stream;
  }

  /** @param resource $stream */
  private static function readStream($stream): string {
    rewind($stream);

    return stream_get_contents($stream) ?: '';
  }
}
