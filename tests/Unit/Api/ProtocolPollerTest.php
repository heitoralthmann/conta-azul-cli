<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\ApiTransportInterface;
use ContaAzulCli\Api\ProtocolPoller;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use PHPUnit\Framework\TestCase;

/** Verifies protocol polling independently from HTTP transport details. */
final class ProtocolPollerTest extends TestCase
{
  /** Successful polling returns nested data and applies exponential delays. */
  public function testPollReturnsSuccessPayload(): void {
    $sleeper = new RecordingSleeper();
    $poller  = new ProtocolPoller(
        new QueueTransport(
            [
              ['status' => 'PENDING'],
              ['status' => 'SUCCESS', 'data' => ['id' => 'event-1']],
            ],
        ),
        $sleeper,
    );

    self::assertSame(['id' => 'event-1'], $poller->poll('protocol-1'));
    self::assertSame([1.0], $sleeper->delays);
  }

  /** Polling failures preserve the protocol id so callers can resume later. */
  public function testPollWrapsTransientFailureWithKnownProtocolId(): void {
    $transport = new class implements ApiTransportInterface {
      /**
       * Always reports a resumable server failure.
       *
       * @param array<string, mixed> $options
       *
       * @return array<mixed>
       */
      // phpcs:ignore Squiz.Commenting.FunctionComment.InvalidNoReturn -- always throws by design, never returns.
      public function request(string $method, string $path, array $options = []): array {
        throw new CliException(ErrorKind::ServerError, true, 'temporary', 500, null, 'correlation-test');
      }

      /**
       * Always reports a resumable server failure.
       *
       * @param array<string, mixed> $options
       */
      // phpcs:ignore Squiz.Commenting.FunctionComment.InvalidNoReturn -- always throws by design, never returns.
      public function requestScalar(string $method, string $path, array $options = []): mixed {
        throw new CliException(ErrorKind::ServerError, true, 'temporary', 500, null, 'correlation-test');
      }

      /** Returns the correlation id attached to the transport. */
      public function getCorrelationId(): string {
        return 'correlation-test';
      }
    };

    try {
      (new ProtocolPoller($transport, new RecordingSleeper()))->poll('protocol-2');
      self::fail('Expected polling to fail');
    } catch (CliException $exception) {
      self::assertSame(ErrorKind::PollDropKnownId, $exception->kind);
      self::assertSame('protocol-2', $exception->protocolId);
      self::assertSame('correlation-test', $exception->correlationId);
    }
  }
}
