<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function count;
use function dirname;
use function file_get_contents;
use function getenv;
use function glob;
use function is_dir;
use function putenv;
use function rmdir;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function uniqid;

final class BaseClientTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];
  private string $tokenPath;

  private const array ENV_VARS = [
    'CA_CLIENT_ID',
    'CA_CLIENT_SECRET',
    'CA_API_BASE_URL',
    'CA_CLI_TOKEN_PATH',
    'CA_BOOTSTRAP_REFRESH_TOKEN',
  ];

  protected function setUp(): void
  {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-test-' . uniqid() . '/tokens.json';
    putenv('CA_CLIENT_ID=id');
    putenv('CA_CLIENT_SECRET=secret');
    putenv('CA_API_BASE_URL=https://api.example.test');
    putenv('CA_CLI_TOKEN_PATH=' . $this->tokenPath);

    $this->storeValidToken('access-current');
  }

  protected function tearDown(): void
  {
    $dir = dirname($this->tokenPath);
    if (is_dir($dir)) {
      array_map('unlink', glob($dir . '/*') ?: []);
      rmdir($dir);
    }

    foreach ($this->originalEnv as $var => $value) {
      if ($value === false) {
        putenv($var);
      } else {
        putenv($var . '=' . $value);
      }
    }
  }

  private function storeValidToken(string $accessToken): void
  {
    (new TokenStore(new Configuration()))->save(
        new TokenData(
            accessToken: $accessToken,
            accessTokenExpiresAt: new DateTimeImmutable('+30 minutes'),
            refreshToken: 'refresh-1',
            refreshTokenObtainedAt: new DateTimeImmutable('-1 hour'),
        ),
    );
  }

  /** @param list<MockResponse>|callable $apiResponses */
  private function client(
      array|callable $apiResponses,
      HttpClientInterface|null $authClient = null,
  ): RecordingBaseClient {
    $config   = new Configuration();
    $store    = new TokenStore($config);
    $oauth    = new OAuthClient($authClient ?? new MockHttpClient([]), $config);
    $redactor = new Redactor();

    return new RecordingBaseClient(
        $config,
        new AuthManager($store, $oauth, $config),
        new Logger($redactor),
        $redactor,
        new MockHttpClient($apiResponses),
    );
  }

  private static function fixture(string $name): string
  {
    $content = file_get_contents(__DIR__ . '/../../fixtures/' . $name);
    self::assertIsString($content);

    return $content;
  }

  // --- Success path -----------------------------------------------------

  // phpcs:ignore Squiz.Commenting.FunctionComment.WrongStyle -- divider comment above, not a docblock.
  public function testSuccessfulGetReturnsDecodedPayload(): void
  {
    $client = $this->client([new MockResponse(self::fixture('lancamentos_list.json'))]);

    $result = $client->request('GET', '/v1/financeiro/lancamentos');

    self::assertSame(1, $result['total']);
    self::assertSame('abc-123', $result['data'][0]['id']);
    self::assertSame([], $client->sleeps);
  }

  public function testRequestCarriesBearerAndCorrelationHeaders(): void
  {
    $captured = null;
    $client   = $this->client(
        static function (string $method, string $url, array $options) use (&$captured) {
          $captured = ['url' => $url, 'headers' => $options['headers']];

          return new MockResponse('{}');
        },
    );

    $client->request('GET', '/v1/financeiro/categorias');

    self::assertIsArray($captured);
    self::assertSame('https://api.example.test/v1/financeiro/categorias', $captured['url']);
    self::assertContains('Authorization: Bearer access-current', $captured['headers']);
    self::assertContains('X-Correlation-Id: ' . $client->getCorrelationId(), $captured['headers']);
  }

  public function testNoContentResponseReturnsEmptyArray(): void
  {
    $client = $this->client([new MockResponse('', ['http_code' => 204])]);

    self::assertSame([], $client->request('DELETE', '/v1/financeiro/contas-a-receber/abc'));
  }

  // --- Retry policy -----------------------------------------------------

  // phpcs:ignore Squiz.Commenting.FunctionComment.WrongStyle -- divider comment above, not a docblock.
  public function testGetRetriesOn429AndThenSucceeds(): void
  {
    $client = $this->client(
        [
          new MockResponse(self::fixture('error_429.json'), ['http_code' => 429]),
          new MockResponse(self::fixture('lancamentos_list.json')),
        ],
    );

    $result = $client->request('GET', '/v1/financeiro/lancamentos');

    self::assertSame(1, $result['total']);
    self::assertSame([0.5], $client->sleeps);
  }

  public function testGetRetriesOn503(): void
  {
    $client = $this->client(
        [
          new MockResponse('gateway down', ['http_code' => 503]),
          new MockResponse('{"ok":true}'),
        ],
    );

    $result = $client->request('GET', '/v1/financeiro/categorias');

    self::assertTrue($result['ok']);
    self::assertSame([0.5], $client->sleeps);
  }

  public function testGetGivesUpAfterThreeAttemptsAndReportsRateLimited(): void
  {
    $client = $this->client(
        [
          new MockResponse(self::fixture('error_429.json'), ['http_code' => 429]),
          new MockResponse(self::fixture('error_429.json'), ['http_code' => 429]),
          new MockResponse(self::fixture('error_429.json'), ['http_code' => 429]),
        ],
    );

    try {
      $client->request('GET', '/v1/financeiro/lancamentos');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::RateLimited, $e->kind);
      self::assertTrue($e->retryable);
    }

    self::assertSame([0.5, 2.0], $client->sleeps, 'Backoff must follow the documented schedule.');
  }

  public function testRetryAfterHeaderOverridesTheBackoffSchedule(): void
  {
    $client = $this->client(
        [
          new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['retry-after' => '5']]),
          new MockResponse('{"ok":true}'),
        ],
    );

    $client->request('GET', '/v1/financeiro/categorias');

    self::assertSame([5.0], $client->sleeps);
  }

  public function testWritesAreNotRetriedOn500AndMapToAmbiguous(): void
  {
    // I1: a write that may have been applied must never be replayed silently.
    $client = $this->client([new MockResponse('boom', ['http_code' => 500])]);

    try {
      $client->request('POST', '/v1/financeiro/contas-a-receber', ['json' => ['valor' => 10]]);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::Ambiguous, $e->kind);
    }

    self::assertSame([], $client->sleeps, 'Writes must not be retried on 5xx.');
  }

  public function testWritesAreRetriedOn429(): void
  {
    // 429 is safe to replay: the request was never processed.
    $client = $this->client(
        [
          new MockResponse(self::fixture('error_429.json'), ['http_code' => 429]),
          new MockResponse('{"protocolId":"p-1"}', ['http_code' => 202]),
        ],
    );

    $result = $client->request('POST', '/v1/financeiro/contas-a-receber', ['json' => []]);

    self::assertSame('p-1', $result['protocolId']);
    self::assertSame([0.5], $client->sleeps);
  }

  public function testClientErrorIsNotRetried(): void
  {
    $client = $this->client([new MockResponse(self::fixture('error_422.json'), ['http_code' => 422])]);

    try {
      $client->request('GET', '/v1/financeiro/lancamentos');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
    }

    self::assertSame([], $client->sleeps);
  }

  // --- Reactive refresh on 401 -----------------------------------------

  // phpcs:ignore Squiz.Commenting.FunctionComment.WrongStyle -- divider comment above, not a docblock.
  public function testUnauthorizedTriggersASingleRefreshAndRetriesWithTheNewToken(): void
  {
    $sentTokens = [];
    $apiClient  = static function (string $method, string $url, array $options) use (&$sentTokens) {
      foreach ($options['headers'] as $header) {
        if (! str_starts_with($header, 'Authorization: ')) {
          continue;
        }

        $sentTokens[] = substr($header, strlen('Authorization: Bearer '));
      }

      return count($sentTokens) === 1
      ? new MockResponse(self::fixture('error_401.json'), ['http_code' => 401])
      : new MockResponse('{"ok":true}');
    };

    $authClient = new MockHttpClient(
        [
          new MockResponse(
              '{"access_token":"access-refreshed","refresh_token":"refresh-2","expires_in":3600}',
          ),
        ],
    );

    $result = $this->client($apiClient, $authClient)->request('GET', '/v1/financeiro/categorias');

    self::assertTrue($result['ok']);
    self::assertSame(['access-current', 'access-refreshed'], $sentTokens);
  }

  public function testUnauthorizedTwiceSurfacesAsAuthFailed(): void
  {
    $apiClient = new MockHttpClient(
        [
          new MockResponse(self::fixture('error_401.json'), ['http_code' => 401]),
          new MockResponse(self::fixture('error_401.json'), ['http_code' => 401]),
        ],
    );

    $authClient = new MockHttpClient(
        [
          new MockResponse(
              '{"access_token":"access-refreshed","refresh_token":"refresh-2","expires_in":3600}',
          ),
        ],
    );

    $config   = new Configuration();
    $redactor = new Redactor();
    $client   = new RecordingBaseClient(
        $config,
        new AuthManager(new TokenStore($config), new OAuthClient($authClient, $config), $config),
        new Logger($redactor),
        $redactor,
        $apiClient,
    );

    $this->expectException(CliException::class);
    $client->request('GET', '/v1/financeiro/categorias');
  }

  // --- Async writes / polling ------------------------------------------

  // phpcs:ignore Squiz.Commenting.FunctionComment.WrongStyle -- divider comment above, not a docblock.
  public function testPollingReturnsThePayloadOnTerminalSuccess(): void
  {
    $client = $this->client(
        [
          new MockResponse(self::fixture('protocolo_pending.json')),
          new MockResponse(self::fixture('protocolo_success.json')),
        ],
    );

    $result = $client->pollProtocol('proto-xyz-123', 60);

    self::assertSame('new-event-456', $result['id']);
    self::assertSame([1.0], $client->sleeps, 'First poll waits the initial backoff.');
  }

  public function testPollingBackoffDoublesAndCapsAtEightSeconds(): void
  {
    $pending = self::fixture('protocolo_pending.json');
    $client  = $this->client(
        [
          new MockResponse($pending),
          new MockResponse($pending),
          new MockResponse($pending),
          new MockResponse($pending),
          new MockResponse($pending),
          new MockResponse(self::fixture('protocolo_success.json')),
        ],
    );

    $client->pollProtocol('proto-xyz-123', 3600);

    self::assertSame([1.0, 2.0, 4.0, 8.0, 8.0], $client->sleeps);
  }

  public function testPollingTimeoutReportsTheKnownProtocolId(): void
  {
    $client = $this->client([new MockResponse(self::fixture('protocolo_pending.json'))]);

    try {
      $client->pollProtocol('proto-xyz-123', 0);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::PollTimeoutKnownId, $e->kind);
      self::assertFalse($e->retryable);
      self::assertSame('proto-xyz-123', $e->protocolId);
      self::assertStringContainsString('ca protocolo get', $e->getMessage());
    }
  }

  public function testTerminalErrorStatusReportsServerErrorWithProtocolId(): void
  {
    $client = $this->client([new MockResponse('{"protocolId":"p-9","status":"ERROR"}')]);

    try {
      $client->pollProtocol('p-9', 60);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ServerError, $e->kind);
      self::assertSame('p-9', $e->protocolId);
    }
  }

  public function testPollingInterruptionReportsTheKnownProtocolId(): void
  {
    $client = $this->client(
        [
          new MockResponse('boom', ['http_code' => 500]),
          new MockResponse('boom', ['http_code' => 500]),
          new MockResponse('boom', ['http_code' => 500]),
        ],
    );

    try {
      $client->pollProtocol('p-7', 60);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::PollDropKnownId, $e->kind);
      self::assertTrue($e->retryable);
      self::assertSame('p-7', $e->protocolId);
    }
  }

  public function testUnrecoverableErrorDuringPollingKeepsItsOwnKind(): void
  {
    // Reporting a 422 as poll_drop_known_id would tell the agent to resume
    // a poll that can never succeed.
    $client = $this->client([new MockResponse(self::fixture('error_422.json'), ['http_code' => 422])]);

    try {
      $client->pollProtocol('p-5', 60);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
      self::assertFalse($e->retryable);
    }
  }

  public function testNoWaitReturnsTheRawAcceptedResponse(): void
  {
    $client = $this->client([]);

    $result = $client->handleAsyncResponse(['protocolId' => 'p-1'], 60, true);

    self::assertSame(['protocolId' => 'p-1'], $result);
    self::assertSame([], $client->sleeps, 'No polling should happen under --no-wait.');
  }

  public function testSynchronousResponseWithoutProtocolIdIsPassedThrough(): void
  {
    $client = $this->client([]);

    $result = $client->handleAsyncResponse(['id' => 'created-1'], 60, false);

    self::assertSame(['id' => 'created-1'], $result);
  }
}
