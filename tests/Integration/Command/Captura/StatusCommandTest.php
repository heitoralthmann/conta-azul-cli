<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Captura;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Captura\StatusCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;

use function array_map;
use function implode;
use function range;

final class StatusCommandTest extends CommandTestCase
{
  public function testQueriesStatusForTheGivenIds(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1, doc-2']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['itens' => []], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  public function testMissingIdsIsRenderedAsClientErrorWithoutCallingTheApi(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, []);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }

  /**
   * This endpoint stops at 20, unlike almost every other listing. Sizes the
   * API refuses must not reach it — `7` used to be the case here, but the
   * endpoint actually accepts it (see the test below).
   */
  #[DataProvider('pageSizesTheEndpointRefuses')]
  public function testPageSizeAboveTheEndpointMaximumIsRenderedAsClientError(string $size): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1', '--tamanho-pagina' => $size]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }

  /** @return list<array{string}> */
  public static function pageSizesTheEndpointRefuses(): array {
    return [['0'], ['21'], ['50'], ['100'], ['1000']];
  }

  /**
   * `captura status` takes any integer in 1..20. Validating it against the
   * discrete steps the other listings use rejected sizes production
   * answers `200` to — measured on 2026-08-19.
   */
  #[DataProvider('pageSizesTheEndpointAccepts')]
  public function testPageSizeWithinTheEndpointRangeReachesTheApi(string $size): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $tester = $this->runCommand($command, ['--ids' => 'doc-1', '--tamanho-pagina' => $size]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());
  }

  /** @return list<array{string}> */
  public static function pageSizesTheEndpointAccepts(): array {
    return [['1'], ['7'], ['15'], ['20']];
  }

  /**
   * The endpoint answers `400` ("O campo 'ids' não pode conter mais de 20
   * itens") above twenty, so the CLI stops it before the round trip.
   */
  public function testMoreThanTwentyIdsIsRejectedWithoutCallingTheApi(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $ids = implode(',', array_map(static fn (int $i): string => 'doc-' . $i, range(1, 21)));

    $tester = $this->runCommand($command, ['--ids' => $ids]);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    self::assertSame('', $output->stdout());
    self::assertSame('client_error', self::decodeEnvelope($output->stderr())['kind']);
  }

  public function testExactlyTwentyIdsIsAccepted(): void {
    $output  = $this->newOutput();
    $command = new StatusCommand(
        $this->capturaClient([$this->jsonResponse(['itens' => []])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $ids = implode(',', array_map(static fn (int $i): string => 'doc-' . $i, range(1, 20)));

    $tester = $this->runCommand($command, ['--ids' => $ids]);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame('', $output->stderr());
  }
}
