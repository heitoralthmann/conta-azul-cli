<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Captura;

use ContaAzulCli\Command\Captura\EnviarCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class EnviarCommandTest extends CommandTestCase
{
  private string $arquivo = '';

  protected function setUp(): void {
    $this->arquivo = sys_get_temp_dir() . '/ca-cli-captura-enviar-' . uniqid() . '.pdf';
    file_put_contents($this->arquivo, '%PDF-1.4 conteudo de teste');
  }

  protected function tearDown(): void {
    // Best-effort cleanup: the temp file may already be gone.
    // phpcs:ignore Generic.PHP.NoSilencedErrors
    @unlink($this->arquivo);
  }

  public function testUploadsTheFileAndRendersTheResponse(): void {
    $output  = $this->newOutput();
    $command = new EnviarCommand(
        $this->capturaClient([$this->jsonResponse(['id' => 'doc-1', 'nome' => 'recibo.pdf'], 201)]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['arquivo' => $this->arquivo, '--descricao' => 'Recibo de teste']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'doc-1', 'nome' => 'recibo.pdf'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingFileIsRenderedAsClientErrorWithoutCallingTheApi(): void {
    $output  = $this->newOutput();
    $command = new EnviarCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['arquivo' => $this->arquivo . '-nao-existe']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', json_decode($output->stderr(), true)['kind']);
  }
}
