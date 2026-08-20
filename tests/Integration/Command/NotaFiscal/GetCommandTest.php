<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\NotaFiscal;

use ContaAzulCli\Command\NotaFiscal\GetCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\MockResponse;

use function base64_decode;

final class GetCommandTest extends CommandTestCase
{
  /**
   * A API devolve XML binário, não JSON; o comando precisa embrulhar o
   * conteúdo em base64 para preservar o contrato de stdout estruturado.
   */
  public function testFetchesTheInvoiceAndBase64EncodesTheBinaryBody(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->notasFiscaisClient(
            [
              new MockResponse(
                  '<nfeProc>...</nfeProc>',
                  ['http_code' => 200, 'response_headers' => ['content-type' => 'application/xml']],
              ),
            ],
        ),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['chave' => '42250323643586000108550010000001151606401726']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $payload = self::decodePayload($output->stdout());
    self::assertSame('application/xml', $payload['content_type']);
    self::assertSame('<nfeProc>...</nfeProc>', base64_decode($payload['content_base64'], true));
    self::assertSame('', $output->stderr());
  }

  public function testNotFoundIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new GetCommand(
        $this->notasFiscaisClient([$this->errorResponse(404, '{"message":"not found"}')]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['chave' => 'chave-inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(404, $envelope['http_status']);
  }
}
