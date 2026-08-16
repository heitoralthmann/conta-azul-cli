<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\ContaAReceber;

use ContaAzulCli\Command\ContaAReceber\CreateCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class CreateCommandTest extends CommandTestCase
{
  public function testCreatesAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([$this->jsonResponse(['id' => 'abc-123', 'descricao' => 'Serviço prestado'])]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['--json' => '{"descricao":"Serviço prestado"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'abc-123', 'descricao' => 'Serviço prestado'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testMissingJsonOptionFailsBeforeAnyApiCall(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, []);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
    self::assertFalse($envelope['retryable']);
  }

  public function testMalformedJsonIsRejectedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['--json' => '{not valid']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }

  public function testApiRejectionIsRenderedAsClientErrorWithHttpStatus(): void {
    $output  = $this->newOutput();
    $command = new CreateCommand(
        $this->financeiroClient([$this->errorResponse(400, '{"message":"data_vencimento é obrigatória"}')]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
    );

    $tester = $this->runCommand($command, ['--json' => '{"descricao":"x"}']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(400, $envelope['http_status']);
  }
}
