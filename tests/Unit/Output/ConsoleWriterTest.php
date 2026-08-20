<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\ConsoleWriter;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Verifies stream routing and raw writes for already-formatted payloads.
 */
final class ConsoleWriterTest extends TestCase
{
  /** Success payloads go to the main stream and always end with one newline. */
  public function testWritesSuccessPayloadToStdout(): void {
    $output = new BufferedOutput();

    (new ConsoleWriter($output))->writeSuccess('{"value":"<keep>"}');

    self::assertSame("{\"value\":\"<keep>\"}\n", $output->fetch());
  }

  /** A trailing newline from the formatter is not doubled. */
  public function testDoesNotDoubleTrailingNewline(): void {
    $output = new BufferedOutput();

    (new ConsoleWriter($output))->writeSuccess("key: value\n");

    self::assertSame("key: value\n", $output->fetch());
  }

  /** Diagnostics use stderr when the output exposes a separate error stream. */
  public function testWritesDiagnosticsToStderr(): void {
    $output = new MemoryConsoleOutput();

    (new ConsoleWriter($output))->writeSuccess('ok');
    (new ConsoleWriter($output))->writeDiagnostic('fail');

    self::assertSame("ok\n", $output->stdout());
    self::assertSame("fail\n", $output->stderr());
  }
}
