<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Error;

use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Error\HttpErrorMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpErrorMapperTest extends TestCase
{
    private HttpErrorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new HttpErrorMapper();
    }

    public function testMaps401ToAuthFailed(): void
    {
        $client = new MockHttpClient([new MockResponse('{"error":"unauthorized"}', ['http_code' => 401])]);
        $response = $client->request('GET', 'https://example.com/test');
        $response->getStatusCode(); // trigger response

        $exception = $this->mapper->mapResponse($response, 'GET', 'corr-id');

        self::assertSame(ErrorKind::AuthFailed, $exception->kind);
        self::assertFalse($exception->retryable);
        self::assertSame(401, $exception->httpStatus);
    }

    public function testMaps429ToRateLimited(): void
    {
        $client = new MockHttpClient([new MockResponse('{}', ['http_code' => 429])]);
        $response = $client->request('GET', 'https://example.com/test');
        $response->getStatusCode();

        $exception = $this->mapper->mapResponse($response, 'GET', 'corr-id');

        self::assertSame(ErrorKind::RateLimited, $exception->kind);
        self::assertTrue($exception->retryable);
    }

    public function testMaps422ToClientError(): void
    {
        $client = new MockHttpClient([new MockResponse('{"error":"validation"}', ['http_code' => 422])]);
        $response = $client->request('GET', 'https://example.com/test');
        $response->getStatusCode();

        $exception = $this->mapper->mapResponse($response, 'GET', 'corr-id');

        self::assertSame(ErrorKind::ClientError, $exception->kind);
        self::assertFalse($exception->retryable);
    }

    public function testMaps500OnGetToServerError(): void
    {
        $client = new MockHttpClient([new MockResponse('internal error', ['http_code' => 500])]);
        $response = $client->request('GET', 'https://example.com/test');
        $response->getStatusCode();

        $exception = $this->mapper->mapResponse($response, 'GET', 'corr-id');

        self::assertSame(ErrorKind::ServerError, $exception->kind);
        self::assertTrue($exception->retryable);
    }

    public function testMaps500OnPostToAmbiguous(): void
    {
        $client = new MockHttpClient([new MockResponse('internal error', ['http_code' => 500])]);
        $response = $client->request('POST', 'https://example.com/test');
        $response->getStatusCode();

        $exception = $this->mapper->mapResponse($response, 'POST', 'corr-id');

        self::assertSame(ErrorKind::Ambiguous, $exception->kind);
        self::assertFalse($exception->retryable);
    }

    public function testMapsTransportErrorToTransient(): void
    {
        $transportError = new \RuntimeException('connection refused');

        $exception = $this->mapper->mapTransportError($transportError, 'corr-id');

        self::assertSame(ErrorKind::Transient, $exception->kind);
        self::assertTrue($exception->retryable);
        self::assertSame($transportError, $exception->getPrevious());
    }
}
