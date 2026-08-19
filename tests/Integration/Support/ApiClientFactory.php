<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Support;

use ContaAzulCli\Api\CapturaClient;
use ContaAzulCli\Api\ContratosClient;
use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\NotasFiscaisClient;
use ContaAzulCli\Api\OrcamentosClient;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Api\ServicosClient;
use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Auth\AccessTokenServiceInterface;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthGatewayInterface;
use ContaAzulCli\Auth\TokenRepositoryInterface;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Builds real (final) API clients wired to a MockHttpClient.
 *
 * The clients are `final` and construct their transport internally, so they
 * cannot be doubled with createMock(). Building the real object with a fake
 * HTTP client — the same seam FinanceiroClientTest already uses — exercises
 * the actual request/response/error-mapping wiring instead of a shallow
 * stand-in.
 *
 * @mixin TestCase
 */
trait ApiClientFactory
{
  /** @param list<MockResponse> $responses */
  protected function financeiroClient(array $responses, string $accessToken = 'test-access-token'): FinanceiroClient {
    return new FinanceiroClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function pessoasClient(array $responses, string $accessToken = 'test-access-token'): PessoasClient {
    return new PessoasClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function produtosClient(array $responses, string $accessToken = 'test-access-token'): ProdutosClient {
    return new ProdutosClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function servicosClient(array $responses, string $accessToken = 'test-access-token'): ServicosClient {
    return new ServicosClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function contratosClient(array $responses, string $accessToken = 'test-access-token'): ContratosClient {
    return new ContratosClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function notasFiscaisClient(
      array $responses,
      string $accessToken = 'test-access-token',
  ): NotasFiscaisClient {
    return new NotasFiscaisClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function vendasClient(array $responses, string $accessToken = 'test-access-token'): VendasClient {
    return new VendasClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function orcamentosClient(array $responses, string $accessToken = 'test-access-token'): OrcamentosClient {
    return new OrcamentosClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /** @param list<MockResponse> $responses */
  protected function capturaClient(array $responses, string $accessToken = 'test-access-token'): CapturaClient {
    return new CapturaClient(...$this->apiClientDependencies($responses, $accessToken));
  }

  /**
   * A FinanceiroClient that records the outgoing request instead of matching
   * a queued response.
   *
   * Client tests cannot catch a wrong payload field name — the client
   * forwards whatever it is handed. Inspecting the request body is the only
   * way to pin the write-side names down, the same way the module tests pin
   * down query parameters.
   *
   * @param array{method: string, url: string, body: string|null}|null $captured
   */
  protected function financeiroClientRecording(array|null &$captured): FinanceiroClient {
    $http = new MockHttpClient(
        static function (string $method, string $url, array $options) use (&$captured): MockResponse {
          $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];

          return new MockResponse('{}', ['http_code' => 200]);
        },
    );

    return new FinanceiroClient(
        $this->testConfiguration(),
        $this->testAuthManager('test-access-token'),
        new Logger(new Redactor()),
        new Redactor(),
        $http,
    );
  }

  /**
   * A CapturaClient that records the outgoing request URL.
   *
   * Only the URL is kept: `MockResponse::fromRequest()` drains a multipart
   * body closure as soon as the factory returns, so touching `$options`
   * ['body'] here would consume the upload stream that the client under
   * test is still building.
   *
   * @param array{method: string, url: string}|null $captured
   */
  protected function capturaClientRecording(array|null &$captured): CapturaClient {
    $http = new MockHttpClient(
        static function (string $method, string $url) use (&$captured): MockResponse {
          $captured = ['method' => $method, 'url' => $url];

          return new MockResponse('{}', ['http_code' => 200]);
        },
    );

    return new CapturaClient(
        $this->testConfiguration(),
        $this->testAuthManager('test-access-token'),
        new Logger(new Redactor()),
        new Redactor(),
        $http,
    );
  }

  /** A 200 response with a JSON-encoded body. */
  protected function jsonResponse(mixed $data, int $status = 200): MockResponse {
    return new MockResponse(
        json_encode($data, JSON_THROW_ON_ERROR),
        ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']],
    );
  }

  /** An error response. Avoid 401/429/502/503/504, which the transport retries. */
  protected function errorResponse(int $status, string $body = '{}'): MockResponse {
    return new MockResponse($body, ['http_code' => $status]);
  }

  /**
   * @param list<MockResponse> $responses
   *
   * @return array{Configuration, AuthManager, Logger, Redactor, MockHttpClient}
   */
  private function apiClientDependencies(array $responses, string $accessToken): array {
    return [
      $this->testConfiguration(),
      $this->testAuthManager($accessToken),
      new Logger(new Redactor()),
      new Redactor(),
      new MockHttpClient($responses),
    ];
  }

  protected function testConfiguration(): Configuration {
    return Configuration::fromValues(
        [
          'apiBaseUrl'            => 'https://api-v2.contaazul.com',
          'authBaseUrl'           => 'https://auth.contaazul.com',
          'authorizeUrl'          => 'https://auth.contaazul.com/oauth2/authorize',
          'bootstrapRefreshToken' => null,
          'callbackCertFile'      => null,
          'callbackKeyFile'       => null,
          'callbackTimeout'       => 300,
          'clientId'              => 'test-client-id',
          'clientSecret'          => 'test-client-secret',
          'redirectUri'           => 'https://example.test/callback',
          'scope'                 => null,
          'tokenPath'             => '/tmp/ca-cli-tests-unused/tokens.json',
          'tokenUrl'              => 'https://auth.contaazul.com/oauth2/token',
        ],
    );
  }

  /** AuthManager backed by mocked collaborators; only getValidAccessToken/refreshAfter401 are wired. */
  private function testAuthManager(string $accessToken): AuthManager {
    $accessTokens = $this->createMock(AccessTokenServiceInterface::class);
    $accessTokens->method('getValidAccessToken')->willReturn($accessToken);
    $accessTokens->method('refreshAfter401')->willReturn($accessToken);

    return new AuthManager(
        $this->createMock(TokenRepositoryInterface::class),
        $this->createMock(OAuthGatewayInterface::class),
        $this->testConfiguration(),
        $accessTokens,
    );
  }
}
