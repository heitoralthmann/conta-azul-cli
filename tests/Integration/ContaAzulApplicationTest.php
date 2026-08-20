<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration;

use ContaAzulCli\ContaAzulApplication;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;

use function getenv;
use function json_decode;
use function putenv;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/** End-to-end coverage for project and Symfony command option boundaries. */
final class ContaAzulApplicationTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  protected function setUp(): void {
    foreach (['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_CLI_TOKEN_PATH'] as $variable) {
      $this->originalEnv[$variable] = getenv($variable);
    }

    putenv('CA_CLIENT_ID=test-client-id');
    putenv('CA_CLIENT_SECRET=test-client-secret');
    putenv('CA_CLI_TOKEN_PATH=/tmp/ca-cli-application-test-unused.json');
  }

  protected function tearDown(): void {
    foreach ($this->originalEnv as $variable => $value) {
      putenv($value === false ? $variable : $variable . '=' . $value);
    }
  }

  /** Symfony discovery defaults to its normal human-readable text. */
  public function testListAndHelpUseSymfonyTextByDefault(): void {
    [$listStatus, $listOutput]         = $this->runCli('list');
    [$helpStatus, $helpOutput]         = $this->runCli('help', 'list');
    [$topLevelStatus, $topLevelOutput] = $this->runCli('--help');

    self::assertSame(0, $listStatus);
    self::assertStringContainsString('Available commands:', $listOutput->stdout());
    self::assertSame('', $listOutput->stderr());

    self::assertSame(0, $helpStatus);
    self::assertStringContainsString('The list command lists all commands', $helpOutput->stdout());
    self::assertSame('', $helpOutput->stderr());

    self::assertSame(0, $topLevelStatus);
    self::assertStringContainsString('The list command lists all commands', $topLevelOutput->stdout());
    self::assertSame('', $topLevelOutput->stderr());
  }

  /** Native descriptor formats and raw output remain owned by Symfony. */
  public function testListKeepsSymfonyFormatsAndRawMode(): void {
    [$jsonStatus, $jsonOutput]         = $this->runCli('list', '--format=json');
    [$xmlStatus, $xmlOutput]           = $this->runCli('list', '--format=xml');
    [$mdStatus, $mdOutput]             = $this->runCli('list', '--format=md');
    [$rstStatus, $rstOutput]           = $this->runCli('list', '--format=rst');
    [$txtStatus, $txtOutput]           = $this->runCli('list', '--format=txt');
    [$rawStatus, $rawOutput]           = $this->runCli('list', '--raw');
    [$helpJsonStatus, $helpJsonOutput] = $this->runCli('help', 'list', '--format=json');
    [$helpRawStatus, $helpRawOutput]   = $this->runCli('help', 'list', '--raw');

    $json     = json_decode($jsonOutput->stdout(), true, 512, JSON_THROW_ON_ERROR);
    $helpJson = json_decode($helpJsonOutput->stdout(), true, 512, JSON_THROW_ON_ERROR);
    self::assertSame(0, $jsonStatus);
    self::assertArrayHasKey('commands', $json);
    self::assertSame(0, $xmlStatus);
    self::assertStringContainsString('<?xml', $xmlOutput->stdout());
    self::assertSame(0, $mdStatus);
    self::assertStringContainsString('* [`pessoa list`](#pessoa list)', $mdOutput->stdout());
    self::assertSame(0, $rstStatus);
    self::assertStringContainsString('- `pessoa list`_', $rstOutput->stdout());
    self::assertSame(0, $txtStatus);
    self::assertStringContainsString('Available commands:', $txtOutput->stdout());
    self::assertSame(0, $rawStatus);
    self::assertStringContainsString('pessoa list', $rawOutput->stdout());
    self::assertSame(0, $helpJsonStatus);
    self::assertSame('list', $helpJson['name']);
    self::assertSame(0, $helpRawStatus);
    self::assertStringContainsString('--raw             To output raw command list', $helpRawOutput->stdout());
  }

  /** Project commands get only the project response option at registration time. */
  public function testApplicationCommandsExposeFormatButNotRaw(): void {
    $application  = new ContaAzulApplication();
    $projectCount = 0;

    foreach ($application->all() as $command) {
      $name = (string) $command->getName();
      if (! str_contains($name, ' ')) {
        continue;
      }

      ++$projectCount;
      $definition = $command->getDefinition();
      self::assertTrue($definition->hasOption('format'), $name);
      self::assertSame('toon', $definition->getOption('format')->getDefault(), $name);
      self::assertFalse($definition->hasOption('raw'), $name);
    }

    self::assertGreaterThan(0, $projectCount);
    self::assertFalse($application->getDefinition()->hasOption('format'));
    self::assertFalse($application->getDefinition()->hasOption('raw'));
  }

  /** Registering the shared --format option must not crowd out an invokable command's own #[Option] attributes. */
  public function testInvokableOptionsSurviveFormatRegistration(): void {
    $application = new ContaAzulApplication();
    $definition  = $application->get('categoria list')->getDefinition();

    self::assertTrue($definition->hasOption('pagina'));
    self::assertTrue($definition->hasOption('tamanho-pagina'));
  }

  /** The removed JSON alias is rejected on project commands before any API request. */
  public function testApplicationCommandRejectsRaw(): void {
    [$status, $output] = $this->runCli('pessoa list', '--raw');

    self::assertSame(1, $status);
    self::assertSame('', $output->stdout());
    self::assertStringContainsString('pessoa list', $output->stderr());
    self::assertStringContainsString('[--format FORMAT]', $output->stderr());
  }

  /** Built-in failures use Symfony diagnostics instead of structured project envelopes. */
  public function testInvalidListFormatUsesSymfonyErrorRendering(): void {
    [$status, $output] = $this->runCli('list', '--format=toon');

    self::assertSame(1, $status);
    self::assertSame('', $output->stdout());
    self::assertStringContainsString('Unsupported format "toon"', $output->stderr());
  }

  /** @return array{int, MemoryConsoleOutput} */
  private function runCli(string ...$arguments): array {
    $application = new ContaAzulApplication();
    $application->setAutoExit(false);
    $output = new MemoryConsoleOutput();
    $status = $application->run(new ArgvInput(['ca', ...$arguments]), $output);

    return [$status, $output];
  }
}
