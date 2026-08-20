<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Venda;

use ContaAzulCli\Command\Venda\ImprimirCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\MockResponse;

use function base64_decode;

final class ImprimirCommandTest extends CommandTestCase
{
  /**
   * A API devolve PDF binário, não JSON; o comando precisa embrulhar o
   * conteúdo em base64 para preservar o contrato de stdout estruturado.
   */
  public function testFetchesThePdfAndBase64EncodesTheBinaryBody(): void {
    $output  = $this->newOutput();
    $command = new ImprimirCommand(
        $this->vendasClient(
            [
              new MockResponse(
                  '%PDF-1.4 ...',
                  ['http_code' => 200, 'response_headers' => ['content-type' => 'application/pdf']],
              ),
            ],
        ),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => '123e4567-e89b-12d3-a456-426614174000']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    $payload = self::decodePayload($output->stdout());
    self::assertSame('application/pdf', $payload['content_type']);
    self::assertSame('%PDF-1.4 ...', base64_decode($payload['content_base64'], true));
    self::assertSame('', $output->stderr());
  }

  public function testNotFoundIsRenderedAsClientError(): void {
    $output  = $this->newOutput();
    $command = new ImprimirCommand(
        $this->vendasClient([$this->errorResponse(404, '{"message":"not found"}')]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, ['id' => 'venda-inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(404, $envelope['http_status']);
  }
}
