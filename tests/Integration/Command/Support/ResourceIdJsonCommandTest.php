<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Support;

use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

use function json_decode;

/**
 * Covers the mechanism ResourceIdJsonCommand gives to every generic partial
 * update command wired by ProdutoCommandModule and ServicoCommandModule.
 */
final class ResourceIdJsonCommandTest extends CommandTestCase
{
  public function testForwardsIdAndDecodedPayloadToTheOperation(): void {
    $output   = $this->newOutput();
    $received = null;
    $command  = new ResourceIdJsonCommand(
        'produto update',
        'Atualiza um produto',
        static function (string $id, array $payload) use (&$received): array {
          $received = [$id, $payload];

          return ['id' => $id, ...$payload];
        },
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        'ID do produto',
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command, ['id' => 'prod-1', '--json' => '{"nome":"Cadeira nova"}']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['prod-1', ['nome' => 'Cadeira nova']], $received);
    self::assertSame(['id' => 'prod-1', 'nome' => 'Cadeira nova'], json_decode($output->stdout(), true));
  }

  public function testMissingJsonOptionFailsBeforeInvokingTheOperation(): void {
    $output  = $this->newOutput();
    $called  = false;
    $command = new ResourceIdJsonCommand(
        'produto update',
        'Atualiza um produto',
        static function () use (&$called): array {
          $called = true;

          return [];
        },
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        'ID do produto',
        'Payload JSON do produto',
    );

    $tester = $this->runCommand($command, ['id' => 'prod-1']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertFalse($called);

    $envelope = json_decode($output->stderr(), true);
    self::assertSame('client_error', $envelope['kind']);
  }
}
