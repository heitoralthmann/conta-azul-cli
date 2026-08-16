<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Support;

use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

/**
 * Covers the mechanism ResourceIdCommand gives to every generic get/delete
 * command wired by ProdutoCommandModule and ServicoCommandModule.
 */
final class ResourceIdCommandTest extends CommandTestCase
{
  public function testForwardsTheIdArgumentToTheOperation(): void {
    $output   = $this->newOutput();
    $received = null;
    $command  = new ResourceIdCommand(
        'produto get',
        'Busca um produto',
        static function (string $id) use (&$received): array {
          $received = $id;

          return ['id' => $id, 'nome' => 'Cadeira'];
        },
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        'ID do produto',
    );

    $tester = $this->runCommand($command, ['id' => 'prod-1']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('prod-1', $received);
    self::assertSame(['id' => 'prod-1', 'nome' => 'Cadeira'], json_decode($output->stdout(), true));
    self::assertSame('', $output->stderr());
  }

  public function testOperationFailureIsRenderedAsErrorEnvelopeOnly(): void {
    $output  = $this->newOutput();
    $command = new ResourceIdCommand(
        'produto get',
        'Busca um produto',
        static function (): never {
            throw new CliException(ErrorKind::ClientError, false, 'Não encontrado.', 404);
        },
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        'ID do produto',
    );

    $tester = $this->runCommand($command, ['id' => 'prod-inexistente']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
    self::assertSame(404, $envelope['http_status']);
  }
}
