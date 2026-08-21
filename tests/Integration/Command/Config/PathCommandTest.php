<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Config;

use ContaAzulCli\Command\Config\PathCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use Symfony\Component\Console\Command\Command;

use function putenv;

final class PathCommandTest extends ConfigCommandTestCase
{
  /** The whole search path is reported, not just the winner. */
  public function testRendersTheResolvedFileAndEveryCandidate(): void {
    $project = $this->writeProjectEnv("CA_CLIENT_ID=abc\n");
    $output  = $this->newOutput();

    $tester = $this->runCommand($this->command($output));

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());

    $payload = self::decodeEnvelope($output->stdout());
    self::assertSame($project, $payload['arquivo']);
    self::assertSame(
        ['CA_CLI_ENV_FILE', 'raiz do projeto', 'diretório do usuário'],
        self::column($payload['candidatos'], 'origem'),
    );
    self::assertSame(
        ['não definido', 'usado', 'ignorado: um candidato anterior venceu'],
        self::column($payload['candidatos'], 'status'),
    );
  }

  /** Inside a PHAR the project candidate reports why it was skipped. */
  public function testExplainsTheCandidateSkippedInsideAPhar(): void {
    $this->writeProjectEnv("CA_CLIENT_ID=abc\n");
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output, '/opt/bin/ca.phar'));

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $payload    = self::decodeEnvelope($output->stdout());
    $candidatos = self::rows($payload['candidatos']);
    self::assertNull($payload['arquivo']);
    self::assertSame('pulado: dentro do PHAR', self::field($candidatos[1], 'status'));
    self::assertNull(self::field($candidatos[1], 'caminho'));
  }

  /** An unreadable override is reported through the normal error envelope. */
  public function testUnreadableOverrideBecomesAStructuredError(): void {
    putenv('CA_CLI_ENV_FILE=' . $this->projectRoot . '/ausente.env');
    $output = $this->newOutput();

    $tester = $this->runCommand($this->command($output));

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
    self::assertStringContainsString('CA_CLI_ENV_FILE', (string) $envelope['message']);
  }

  private function command(MemoryConsoleOutput $output, string|null $pharPath = null): PathCommand {
    return new PathCommand(
        $this->locator($pharPath),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );
  }
}
