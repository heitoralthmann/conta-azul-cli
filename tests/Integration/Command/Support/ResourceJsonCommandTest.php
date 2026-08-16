<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Support;

use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

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
        new JsonRenderer($output),
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command, ['--json' => '{"nome":"Cadeira"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['nome' => 'Cadeira'], $received);
    self::assertSame(['id' => 'prod-novo', 'nome' => 'Cadeira'], json_decode($output->stdout(), true));
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
        new JsonRenderer($output),
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertFalse($called);
    self::assertSame('', $output->stdout());

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }
}
