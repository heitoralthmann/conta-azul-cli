<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Config;

use ContaAzulCli\Command\Config\InitCommand;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use Symfony\Component\Console\Command\Command;

use function file_get_contents;

final class InitCommandTest extends ConfigCommandTestCase
{
  /** The file lands in the user directory with credential-grade permissions. */
  public function testCreatesThePrivateUserFileFromTheEmbeddedTemplate(): void {
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output));
    $path   = $this->userDirectory() . '/.env';

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());
    self::assertFileExists($path);
    self::assertPermissions('600', $path);
    self::assertPermissions('700', $this->userDirectory());

    $contents = (string) file_get_contents($path);
    self::assertStringContainsString('CA_CLIENT_ID=', $contents);
    self::assertStringContainsString('CA_CLIENT_SECRET=', $contents);
    self::assertSame($path, self::decodeEnvelope($output->stdout())['arquivo']);
  }

  /**
   * `init` targets the user file even when another candidate is in effect.
   *
   * That asymmetry with `config set` is the command's entire reason to exist:
   * it creates the file that makes the CLI usable outside the project.
   */
  public function testAlwaysTargetsTheUserFileEvenWhenTheProjectFileWins(): void {
    $this->writeProjectEnv("CA_CLIENT_ID=do-projeto\n");
    $output = $this->newOutput();

    $this->runCommand($this->command($output));

    self::assertSame(
        $this->userDirectory() . '/.env',
        self::decodeEnvelope($output->stdout())['arquivo'],
    );
  }

  /** Overwriting credentials by accident is the failure mode worth refusing. */
  public function testRefusesToOverwriteAnExistingFile(): void {
    $path   = $this->writeUserEnv("CA_CLIENT_ID=nao-perder\n");
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output));

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertStringContainsString('--force', (string) self::decodeEnvelope($output->stderr())['message']);
    self::assertSame("CA_CLIENT_ID=nao-perder\n", file_get_contents($path));
  }

  /** With --force the operator has said the loss is intentional. */
  public function testForceOverwritesTheExistingFile(): void {
    $path = $this->writeUserEnv("CA_CLIENT_ID=nao-perder\n");

    $tester = $this->runCommand($this->command($this->newOutput()), ['--force' => true]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringNotContainsString('nao-perder', (string) file_get_contents($path));
  }

  private function command(MemoryConsoleOutput $output): InitCommand {
    return new InitCommand(
        $this->locator(),
        new EnvFileWriter(),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );
  }
}
