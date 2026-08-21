<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Config;

use ContaAzulCli\Command\Config\ShowCommand;
use ContaAzulCli\Config\EnvironmentSnapshot;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use Symfony\Component\Console\Command\Command;

use function array_combine;
use function is_string;

final class ShowCommandTest extends ConfigCommandTestCase
{
  /** Every known variable is reported, with the file as the origin of what it defines. */
  public function testReportsFileValuesWithTheirOrigin(): void {
    $this->writeProjectEnv("CA_CLIENT_ID=abc-123\nCA_API_BASE_URL=https://exemplo.test\n");
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output));

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());

    $rows = $this->indexByKey($output->stdout());
    self::assertSame(['arquivo', 'abc-123'], $rows['CA_CLIENT_ID']);
    self::assertSame(['arquivo', 'https://exemplo.test'], $rows['CA_API_BASE_URL']);
  }

  /** A variable the operator exported outranks the file, and says so. */
  public function testEnvironmentOutranksTheFile(): void {
    $this->writeProjectEnv("CA_CLIENT_ID=from-file\n");
    $output = $this->newOutput();

    $tester = $this->runCommand(
        $this->command($output, EnvironmentSnapshot::fromArray(['CA_CLIENT_ID' => 'from-environment'])),
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['ambiente', 'from-environment'], $this->indexByKey($output->stdout())['CA_CLIENT_ID']);
  }

  /** An unset variable falls back to the compiled default, labelled as such. */
  public function testUnsetVariablesShowTheCompiledDefault(): void {
    $output = $this->newOutput();

    $this->runCommand($this->command($output));

    $rows = $this->indexByKey($output->stdout());
    self::assertSame(['default', 'https://api-v2.contaazul.com'], $rows['CA_API_BASE_URL']);
    self::assertSame(['default', '300'], $rows['CA_CALLBACK_TIMEOUT']);
  }

  /** An empty assignment is absent, exactly as the loader treats it. */
  public function testAnEmptyAssignmentCountsAsAbsent(): void {
    $this->writeProjectEnv("CA_API_BASE_URL=\n");
    $output = $this->newOutput();

    $this->runCommand($this->command($output));

    self::assertSame(
        ['default', 'https://api-v2.contaazul.com'],
        $this->indexByKey($output->stdout())['CA_API_BASE_URL'],
    );
  }

  /**
   * The output is meant to be pasted into a bug report, so it must carry no
   * secret at all — not even a prefix.
   */
  public function testNeverPrintsASecret(): void {
    $this->writeProjectEnv("CA_CLIENT_SECRET=super-secret-value\nCA_BOOTSTRAP_REFRESH_TOKEN=refresh-value\n");
    $output = $this->newOutput();

    $this->runCommand($this->command($output));

    $stdout = $output->stdout();
    self::assertStringNotContainsString('super-secret-value', $stdout);
    self::assertStringNotContainsString('refresh-value', $stdout);
    self::assertStringNotContainsString('super', $stdout);

    $rows = $this->indexByKey($stdout);
    self::assertSame(['arquivo', '(definido)'], $rows['CA_CLIENT_SECRET']);
    self::assertSame(['arquivo', '(definido)'], $rows['CA_BOOTSTRAP_REFRESH_TOKEN']);
  }

  /** A credential nobody configured reads as absent, not as an empty string. */
  public function testAbsentSecretsAreReportedAsAbsent(): void {
    $output = $this->newOutput();

    $this->runCommand($this->command($output));

    self::assertSame(['default', '(ausente)'], $this->indexByKey($output->stdout())['CA_CLIENT_SECRET']);
  }

  /** A malformed file gets an actionable error instead of an uncaught fatal. */
  public function testMalformedFileBecomesAStructuredError(): void {
    $this->writeProjectEnv("CA_CLIENT_ID=\"sem fim\n");
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output));

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }

  /**
   * Reads the rendered table back as key => [origin, value].
   *
   * @return array<string, array{0: mixed, 1: mixed}>
   */
  private function indexByKey(string $stdout): array {
    $payload = self::decodeEnvelope($stdout);
    $keys    = [];
    $pairs   = [];

    foreach (self::rows($payload['valores']) as $row) {
      $key = self::field($row, 'chave');
      if (! is_string($key)) {
        self::fail('Cada linha de "valores" precisa ter uma chave textual.');
      }

      $keys[]  = $key;
      $pairs[] = [self::field($row, 'origem'), self::field($row, 'valor')];
    }

    return array_combine($keys, $pairs);
  }

  private function command(
      MemoryConsoleOutput $output,
      EnvironmentSnapshot|null $snapshot = null,
  ): ShowCommand {
    return new ShowCommand(
        $this->locator(),
        $snapshot ?? EnvironmentSnapshot::fromArray([]),
        new Redactor(),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );
  }
}
