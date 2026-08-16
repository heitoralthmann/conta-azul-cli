<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\ApiTransportInterface;
use ContaAzulCli\Api\ProtocolPoller;
use ContaAzulCli\Api\SleeperInterface;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use PHPUnit\Framework\TestCase;

/** Records delays without sleeping during protocol polling tests. */
final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $delays = [];


    /** Records a requested delay. */
    public function sleep(float $seconds): void {
        $this->delays[] = $seconds;
    }


}

/** Supplies deterministic protocol responses to the polling service. */
final class QueueTransport implements ApiTransportInterface
{


    /** @param list<array<mixed>> $responses */
    public function __construct(private array $responses) {
    }


    /** Returns the next protocol response. */
    public function request(string $method, string $path, array $options=[]): array {
        return array_shift($this->responses) ?? [];
    }


    /** Returns a stable correlation id for generated polling errors. */
    public function getCorrelationId(): string {
        return 'correlation-test';
    }


}

/** Verifies protocol polling independently from HTTP transport details. */
final class ProtocolPollerTest extends TestCase
{


    /** Successful polling returns nested data and applies exponential delays. */
    public function testPollReturnsSuccessPayload(): void {
        $sleeper = new RecordingSleeper();
        $poller = new ProtocolPoller(
          new QueueTransport(
            [
                ['status' => 'PENDING'],
                ['status' => 'SUCCESS', 'data' => ['id' => 'event-1']],
            ]
          ),
          $sleeper,
        );

        self::assertSame(['id' => 'event-1'], $poller->poll('protocol-1'));
        self::assertSame([1.0], $sleeper->delays);
    }


    /** Polling failures preserve the protocol id so callers can resume later. */
    public function testPollWrapsTransientFailureWithKnownProtocolId(): void {
        $transport = new class implements ApiTransportInterface {


            /** Always reports a resumable server failure. */
            public function request(string $method, string $path, array $options=[]): array {
                throw new CliException(ErrorKind::ServerError, TRUE, 'temporary', 500, NULL, 'correlation-test');
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
