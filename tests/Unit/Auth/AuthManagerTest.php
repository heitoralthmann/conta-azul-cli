<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function array_map;
use function dirname;
use function getenv;
use function glob;
use function is_dir;
use function json_encode;
use function parse_str;
use function parse_url;
use function putenv;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_QUERY;

final class AuthManagerTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];
  private string $tokenPath;

  private const array ENV_VARS = [
    'CA_CLIENT_ID',
    'CA_CLIENT_SECRET',
    'CA_REDIRECT_URI',
    'CA_SCOPE',
    'CA_AUTHORIZE_URL',
    'CA_CLI_TOKEN_PATH',
    'CA_BOOTSTRAP_REFRESH_TOKEN',
  ];

  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-test-' . uniqid() . '/tokens.json';
    putenv('CA_CLIENT_ID=my-client');
    putenv('CA_CLIENT_SECRET=my-secret');
    putenv('CA_CLI_TOKEN_PATH=' . $this->tokenPath);
  }

  protected function tearDown(): void {
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

  /** @param list<MockResponse> $responses */
  private function manager(array $responses = []): AuthManager {
    $config = new Configuration();
    $store  = new TokenStore($config);
    $oauth  = new OAuthClient(new MockHttpClient($responses), $config);

    return new AuthManager($store, $oauth, $config);
  }

  private function persistToken(string $access, string $refresh, string $expiresAt): void {
    $config = new Configuration();
    (new TokenStore($config))->save(
        new TokenData(
            accessToken: $access,
            accessTokenExpiresAt: new DateTimeImmutable($expiresAt),
            refreshToken: $refresh,
            refreshTokenObtainedAt: new DateTimeImmutable('-1 hour'),
        ),
    );
  }

  private function loadPersisted(): TokenData {
    $token = (new TokenStore(new Configuration()))->load();
    self::assertNotNull($token);

    return $token;
  }

  private static function tokenResponse(string $access, string $refresh): MockResponse {
    return new MockResponse(
        json_encode(
            [
              'access_token'  => $access,
              'refresh_token' => $refresh,
              'expires_in'    => 3600,
            ],
            JSON_THROW_ON_ERROR,
        ),
    );
  }

  public function testReturnsStoredTokenWithoutRefreshingWhenStillValid(): void {
    $this->persistToken('access-valid', 'refresh-1', '+30 minutes');

    // No mock responses queued: any HTTP call would blow up the test.
    $accessToken = $this->manager()->getValidAccessToken();

    self::assertSame('access-valid', $accessToken);
    self::assertSame('refresh-1', $this->loadPersisted()->refreshToken);
  }

  public function testRefreshesPreemptivelyWhenTokenExpiresWithinSixtySeconds(): void {
    $this->persistToken('access-stale', 'refresh-1', '+30 seconds');

    $accessToken = $this->manager([self::tokenResponse('access-fresh', 'refresh-2')])
          ->getValidAccessToken();

    self::assertSame('access-fresh', $accessToken);
  }

  public function testRotatedRefreshTokenIsPersisted(): void {
    $this->persistToken('access-stale', 'refresh-1', '-1 minute');

    $this->manager([self::tokenResponse('access-fresh', 'refresh-2-rotated')])
          ->getValidAccessToken();

    // Losing the rotated token would strand the CLI on the next invocation.
    self::assertSame('refresh-2-rotated', $this->loadPersisted()->refreshToken);
    self::assertSame('access-fresh', $this->loadPersisted()->accessToken);
  }

  public function testFailsWithAuthFailedWhenNoTokenIsStored(): void {
    try {
      $this->manager()->getValidAccessToken();
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::AuthFailed, $e->kind);
      self::assertStringContainsString('ca auth login', $e->getMessage());
    }
  }

  public function testBootstrapRefreshTokenIsExchangedAndPersisted(): void {
    putenv('CA_BOOTSTRAP_REFRESH_TOKEN=bootstrap-token');

    $accessToken = $this->manager([self::tokenResponse('access-ci', 'refresh-ci')])
          ->getValidAccessToken();

    self::assertSame('access-ci', $accessToken);
    // After the first run the env var can be dropped: the file now carries the token.
    self::assertSame('refresh-ci', $this->loadPersisted()->refreshToken);
  }

  public function testRefreshAfter401ReturnsAndPersistsANewToken(): void {
    $this->persistToken('access-rejected', 'refresh-1', '+30 minutes');

    $accessToken = $this->manager([self::tokenResponse('access-after-401', 'refresh-2')])
          ->refreshAfter401();

    self::assertSame('access-after-401', $accessToken);
    self::assertSame('refresh-2', $this->loadPersisted()->refreshToken);
  }

  public function testRefreshAfter401FailsWhenNoTokenIsStored(): void {
    $this->expectException(CliException::class);

    $this->manager()->refreshAfter401();
  }

  public function testInvalidGrantOnRefreshSurfacesAsAuthFailed(): void {
    $this->persistToken('access-stale', 'refresh-consumed', '-1 minute');

    try {
      $this->manager([new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400])])
            ->getValidAccessToken();
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::AuthFailed, $e->kind);
    }
  }

  public function testLogoutRemovesStoredCredentials(): void {
    $this->persistToken('access-1', 'refresh-1', '+30 minutes');

    $this->manager()->logout();

    self::assertFileDoesNotExist($this->tokenPath);
  }

  public function testAuthorizationUrlCarriesTheGeneratedState(): void {
    $manager = $this->manager();

    $url = $manager->startLoginFlow();

    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    self::assertSame('code', $query['response_type']);
    self::assertSame('my-client', $query['client_id']);
    self::assertSame($manager->getPendingState(), $query['state']);
    self::assertNotEmpty($query['state']);
  }

  public function testAuthorizationUrlOmitsScopeWhenUnset(): void {
    // Conta Azul rejects scopes not enabled for the app; omitting lets the
    // provider apply whatever the app is configured for.
    $url = $this->manager()->startLoginFlow();

    self::assertStringNotContainsString('scope', $url);
  }

  public function testAuthorizationUrlIncludesScopeWhenSet(): void {
    putenv('CA_SCOPE=sales');

    $url = $this->manager()->startLoginFlow();

    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    self::assertSame('sales', $query['scope']);
  }

  public function testAuthorizationUrlUsesTheConfiguredAuthorizeEndpoint(): void {
    putenv('CA_AUTHORIZE_URL=https://login.contaazul.com/#/oauth/authorize');

    $url = $this->manager()->startLoginFlow();

    // The production endpoint is a fragment route, so the query has to land
    // after the hash rather than being parsed as a server-side URL.
    self::assertStringStartsWith('https://login.contaazul.com/#/oauth/authorize?', $url);
    self::assertStringContainsString('response_type=code', $url);
  }

  public function testAuthorizationUrlAppendsToAnExistingQueryString(): void {
    putenv('CA_AUTHORIZE_URL=https://login.contaazul.com/oauth/authorize?tenant=acme');

    $url = $this->manager()->startLoginFlow();

    self::assertStringContainsString('?tenant=acme&response_type=code', $url);
  }

  public function testCompleteLoginFlowPersistsTheExchangedToken(): void {
    $manager = $this->manager([self::tokenResponse('access-new', 'refresh-new')]);
    $manager->startLoginFlow();

    $manager->completeLoginFlow('the-code');

    self::assertSame('access-new', $this->loadPersisted()->accessToken);
    self::assertNull($manager->getPendingState());
  }
}
