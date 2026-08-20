<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Support;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function is_array;
use function rtrim;

/** Base class for command contract tests: exit code, stdout, and stderr. */
abstract class CommandTestCase extends TestCase
{
  use ApiClientFactory;

  /** Builds a fresh output double for one command invocation. */
  protected function newOutput(): MemoryConsoleOutput {
    return new MemoryConsoleOutput();
  }

  /**
   * Runs a command through Symfony's CommandTester and returns it for
   * exit-code assertions. Formatted stdout/stderr content is read separately
   * from the MemoryConsoleOutput passed into the command's collaborators,
   * since commands write through it directly, not through CommandTester's
   * own output.
   *
   * @param array<string, mixed> $input
   */
  protected function runCommand(Command $command, array $input = []): CommandTester {
    $application = new Application();
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $application->addCommand($command);

    $tester = new CommandTester($command);
    $tester->execute($input, ['decorated' => false]);

    return $tester;
  }

  /** Decodes a TOON success payload written to stdout. */
  protected static function decodePayload(string $output): mixed {
    return Toon::decode(rtrim($output, "\n"));
  }

  /**
   * Decodes a TOON error or warning envelope written to stderr.
   *
   * @return array<string, mixed>
   */
  protected static function decodeEnvelope(string $output): array {
    $decoded = self::decodePayload($output);
    if (! is_array($decoded)) {
      self::fail('Expected a TOON object envelope.');
    }

    return $decoded;
  }
}
