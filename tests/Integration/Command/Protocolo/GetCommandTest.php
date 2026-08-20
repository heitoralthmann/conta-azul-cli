<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Protocolo;

use ContaAzulCli\Command\Protocolo\GetCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

final class GetCommandTest extends CommandTestCase
{
  public function testFetchesProtocolStatusAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->financeiroClient([$this->jsonResponse(['protocolId' => 'proto-1', 'status' => 'SUCCESS'])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'proto-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['protocolId' => 'proto-1', 'status' => 'SUCCESS'], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testUnknownProtocolIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->financeiroClient([$this->errorResponse(404, '{"message":"não encontrado"}')]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'proto-inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(404, $envelope['http_status']);
  }
}
