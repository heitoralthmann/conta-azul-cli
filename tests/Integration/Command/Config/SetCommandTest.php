<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Config;

use ContaAzulCli\Command\Config\SetCommand;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use Symfony\Component\Console\Command\Command;

use function chmod;
use function decoct;
use function file_get_contents;
use function fileperms;

final class SetCommandTest extends ConfigCommandTestCase
{
  /** The variable is upserted into the file that is actually in effect. */
  public function testWritesIntoTheResolvedFilePreservingComments(): void {
    $path   = $this->writeProjectEnv("# credenciais\nCA_CLIENT_ID=antigo\n");
    $output = $this->newOutput();

    $tester = $this->runCommand(
        $this->command($output),
        ['chave' => 'CA_CLIENT_ID', 'valor' => 'novo'],
    );

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());
    self::assertSame("# credenciais\nCA_CLIENT_ID=novo\n", file_get_contents($path));

    $payload = self::decodeEnvelope($output->stdout());
    self::assertSame($path, $payload['arquivo']);
    self::assertSame('CA_CLIENT_ID', $payload['chave']);
  }

  /**
   * Unlike `init`, `set` follows the search path: the user file only gets
   * written when it is the candidate that won.
   */
  public function testFollowsTheSearchPathRatherThanAlwaysUsingTheUserFile(): void {
    $userFile = $this->writeUserEnv("CA_CLIENT_ID=do-usuario\n");
    $project  = $this->writeProjectEnv("CA_CLIENT_ID=do-projeto\n");

    $this->runCommand($this->command($this->newOutput()), ['chave' => 'CA_CLIENT_ID', 'valor' => 'novo']);

    self::assertSame("CA_CLIENT_ID=novo\n", file_get_contents($project));
    self::assertSame("CA_CLIENT_ID=do-usuario\n", file_get_contents($userFile));
  }

  /** The written value never comes back on stdout: this is how secrets get set. */
  public function testNeverEchoesTheValueItWrote(): void {
    $this->writeProjectEnv("CA_CLIENT_SECRET=\n");
    $output = $this->newOutput();

    $this->runCommand(
        $this->command($output),
        ['chave' => 'CA_CLIENT_SECRET', 'valor' => 'super-secret-value'],
    );

    self::assertStringNotContainsString('super-secret-value', $output->stdout());
    self::assertStringNotContainsString('super-secret-value', $output->stderr());
  }

  /** A typo has to fail loudly, not become a variable that never takes effect. */
  public function testRejectsAnUnknownVariable(): void {
    $path   = $this->writeProjectEnv("CA_CLIENT_ID=antigo\n");
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output), ['chave' => 'CA_CLIENT_IDD', 'valor' => 'x']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertStringContainsString(
        'CA_CLIENT_IDD',
        (string) self::decodeEnvelope($output->stderr())['message'],
    );
    self::assertSame("CA_CLIENT_ID=antigo\n", file_get_contents($path));
  }

  /** A lower-case name is accepted and normalized, so nobody retypes it. */
  public function testNormalizesTheVariableName(): void {
    $path = $this->writeProjectEnv("CA_CLIENT_ID=antigo\n");

    $tester = $this->runCommand($this->command($this->newOutput()), ['chave' => 'ca_client_id', 'valor' => 'novo']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame("CA_CLIENT_ID=novo\n", file_get_contents($path));
  }

  /** With nothing to write to, the error has to name the command that fixes it. */
  public function testPointsAtConfigInitWhenNoFileResolves(): void {
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output), ['chave' => 'CA_CLIENT_ID', 'valor' => 'x']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertStringContainsString(
        'ca config init',
        (string) self::decodeEnvelope($output->stderr())['message'],
    );
  }

  /** A file loosened by hand tightens again on the next write. */
  public function testReappliesRestrictivePermissions(): void {
    $path = $this->writeProjectEnv("CA_CLIENT_ID=antigo\n");
    chmod($path, 0644);

    $this->runCommand($this->command($this->newOutput()), ['chave' => 'CA_CLIENT_ID', 'valor' => 'novo']);

    self::assertSame('600', decoct((int) fileperms($path) & 0777));
  }

  private function command(MemoryConsoleOutput $output): SetCommand {
    return new SetCommand(
        $this->locator(),
        new EnvFileWriter(),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );
  }
}
