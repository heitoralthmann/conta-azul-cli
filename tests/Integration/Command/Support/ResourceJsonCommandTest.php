<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Support;

use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * Covers the mechanism ResourceJsonCommand gives to every generic create
 * command wired by ProdutoCommandModule and ServicoCommandModule.
 */
final class ResourceJsonCommandTest extends CommandTestCase
{
  public function testForwardsTheDecodedPayloadToTheOperation(): void {
    $output   = $this->newOutput();
    $received = null;
    $command  = new ResourceJsonCommand(
        'produto create',
        'Cria um produto',
        static function (array $payload) use (&$received): array {
          $received = $payload;

          return ['id' => 'prod-novo'] + $payload;
        },
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command, ['--json' => '{"nome":"Cadeira"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['nome' => 'Cadeira'], $received);
    self::assertSame(['id' => 'prod-novo', 'nome' => 'Cadeira'], self::decodePayload($output->stdout()));
  }

  public function testMissingJsonOptionFailsBeforeInvokingTheOperation(): void {
    $output  = $this->newOutput();
    $called  = false;
    $command = new ResourceJsonCommand(
        'produto create',
        'Cria um produto',
        static function () use (&$called): array {
          $called = true;

          return [];
        },
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertFalse($called);
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
  }
}
