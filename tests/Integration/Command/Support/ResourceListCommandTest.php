<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Support;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * Covers the mechanism ResourceListCommand gives to every generic list
 * command wired by ProdutoCommandModule and ServicoCommandModule (11 + 5
 * commands), instead of duplicating this test per resource.
 */
final class ResourceListCommandTest extends CommandTestCase
{
  public function testForwardsPaginationAndFiltersToTheOperation(): void {
    $output = $this->newOutput();
    /** @var array{int, int, array<string, mixed>}|null $received */
    $received = null;
    $command  = new ResourceListCommand(
        'produto list',
        'Lista produtos',
        static function (int $page, int $pageSize, array $filters) use (&$received): array {
          $received = [$page, $pageSize, $filters];

          return ['items' => [], 'page' => $page];
        },
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        ['busca' => 'busca'],
    );

    $tester = $this->runCommand($command, ['--pagina' => '2', '--tamanho-pagina' => '10', '--busca' => 'cadeira']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame([2, 10, ['busca' => 'cadeira']], $received);
    self::assertSame(['items' => [], 'page' => 2], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testOperationFailureIsRenderedAsErrorEnvelopeOnly(): void {
    $output  = $this->newOutput();
    $command = new ResourceListCommand(
        'produto list',
        'Lista produtos',
        static function (): never {
          throw new CliException(ErrorKind::RateLimited, true, 'Limite atingido.', 429);
        },
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('rate_limited', $envelope['kind']);
    self::assertTrue($envelope['retryable']);
  }

  public function testUnsupportedPageSizeFailsBeforeInvokingTheOperation(): void {
    $output  = $this->newOutput();
    $called  = false;
    $command = new ResourceListCommand(
        'produto list',
        'Lista produtos',
        static function () use (&$called): array {
          $called = true;

          return [];
        },
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--tamanho-pagina' => '0']);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertFalse($called);

    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
  }
}
