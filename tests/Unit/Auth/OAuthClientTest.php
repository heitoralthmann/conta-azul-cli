<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class OAuthClientTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private const ENV_VARS = [
    'CA_CLIENT_ID',
    'CA_CLIENT_SECRET',
    'CA_REDIRECT_URI',
    'CA_AUTH_BASE_URL',
    'CA_AUTHORIZE_URL',
    'CA_TOKEN_URL',
    'CA_BOOTSTRAP_REFRESH_TOKEN',
  ];


  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    putenv('CA_CLIENT_ID=my-client');
    putenv('CA_CLIENT_SECRET=my-secret');
    putenv('CA_REDIRECT_URI=https://conta-azul-cli.ddev.site:9876/callback');
  }


  protected function tearDown(): void {
    foreach ($this->originalEnv as $var => $value) {
      if ($value === FALSE) {
        putenv($var);
      } else {
        putenv("{$var}={$value}");
      }
    }
  }


  public function testExchangeCodeSendsAuthorizationCodeGrant(): void {
    $captured = NULL;
    $client   = new MockHttpClient(
      function (string $method, string $url, array $options) use (&$captured) {
        $captured = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(
          json_encode(
            [
              'access_token'  => 'access-1',
              'refresh_token' => 'refresh-1',
              'expires_in'    => 3600,
              'token_type'    => 'Bearer',
            ], JSON_THROW_ON_ERROR
          )
        );
      }
    );

    $token = (new OAuthClient($client, new Configuration()))->exchangeCode('the-code');

    self::assertSame('access-1', $token->accessToken);
    self::assertSame('refresh-1', $token->refreshToken);
    self::assertIsArray($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://auth.contaazul.com/oauth2/token', $captured['url']);

    parse_str($captured['options']['body'], $body);
    self::assertSame('authorization_code', $body['grant_type']);
    self::assertSame('the-code', $body['code']);
    self::assertSame('https://conta-azul-cli.ddev.site:9876/callback', $body['redirect_uri']);
  }


  public function testClientCredentialsAreSentAsBasicAuthHeader(): void {
    $captured = NULL;
    $client   = new MockHttpClient(
      function (string $method, string $url, array $options) use (&$captured) {
        $captured = $options;

        return new MockResponse('{"access_token":"a","refresh_token":"r","expires_in":3600}');
      }
    );

    (new OAuthClient($client, new Configuration()))->refresh('some-refresh');

    self::assertIsArray($captured);
    $expected = 'Authorization: Basic ' . base64_encode('my-client:my-secret');
    self::assertContains($expected, $captured['headers']);
  }


  public function testRefreshSendsRefreshTokenGrant(): void {
    $captured = NULL;
    $client   = new MockHttpClient(
      function (string $method, string $url, array $options) use (&$captured) {
        $captured = $options;

        return new MockResponse('{"access_token":"a","refresh_token":"r","expires_in":3600}');
      }
    );

    (new OAuthClient($client, new Configuration()))->refresh('old-refresh');

    self::assertIsArray($captured);
    parse_str($captured['body'], $body);
    self::assertSame('refresh_token', $body['grant_type']);
    self::assertSame('old-refresh', $body['refresh_token']);
  }


  public function testRefreshReturnsTheRotatedRefreshToken(): void {
    // Cognito rotates the refresh token on every use; the new value must win.
    $client = new MockHttpClient(
      [new MockResponse(
        json_encode(
          [
            'access_token'  => 'access-2',
            'refresh_token' => 'refresh-2-rotated',
            'expires_in'    => 3600,
          ], JSON_THROW_ON_ERROR
        )
      )
      ]
    );

    $token = (new OAuthClient($client, new Configuration()))->refresh('refresh-1-old');

    self::assertSame('refresh-2-rotated', $token->refreshToken);
    self::assertNotSame('refresh-1-old', $token->refreshToken);
  }


  public function testInvalidGrantMapsToAuthFailed(): void {
    $client = new MockHttpClient(
      [
        new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->refresh('consumed-token');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::AuthFailed, $e->kind);
      self::assertFalse($e->retryable);
      self::assertStringContainsString('ca auth login', $e->getMessage());
    }
  }


  public function testInvalidGrantOnCodeExchangeBlamesTheCodeNotTheRefreshToken(): void {
    // During login there is no refresh token yet; blaming it would send the
    // operator to re-run the very command that just failed.
    $client = new MockHttpClient(
      [
        new MockResponse(
          '{"error":"invalid_grant","error_description":"Authorization code expired"}',
          ['http_code' => 400],
        ),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->exchangeCode('stale-code');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::AuthFailed, $e->kind);
      self::assertStringContainsString('Código de autorização', $e->getMessage());
      self::assertStringNotContainsString('Refresh token', $e->getMessage());
    }
  }


  public function testInvalidGrantPointsAtMismatchedAuthorizeAndTokenHosts(): void {
    // The failure that cost us an afternoon: the login screen behaves, the
    // callback carries a code, and the exchange still fails — because the
    // code was minted by a different authorization server.
    putenv('CA_AUTHORIZE_URL=https://login.contaazul.com/#/oauth/authorize');
    $client = new MockHttpClient(
      [
        new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->exchangeCode('foreign-code');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertStringContainsString('login.contaazul.com', $e->getMessage());
      self::assertStringContainsString('auth.contaazul.com', $e->getMessage());
      self::assertStringContainsString('CA_AUTHORIZE_URL', $e->getMessage());
    }
  }


  public function testInvalidGrantStaysQuietWhenTheEndpointsAgree(): void {
    $client = new MockHttpClient(
      [
        new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->exchangeCode('stale-code');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertStringNotContainsString('CA_AUTHORIZE_URL', $e->getMessage());
    }
  }


  public function testProviderErrorDescriptionIsCarriedIntoTheMessage(): void {
    $client = new MockHttpClient(
      [
        new MockResponse(
          '{"error":"invalid_grant","error_description":"redirect_uri mismatch"}',
          ['http_code' => 400],
        ),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->exchangeCode('some-code');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertStringContainsString('redirect_uri mismatch', $e->getMessage());
      self::assertStringContainsString('invalid_grant', $e->getMessage());
    }
  }


  public function testMissingProviderDetailStillReportsTheStatus(): void {
    $client = new MockHttpClient([new MockResponse('', ['http_code' => 400])]);

    try {
      (new OAuthClient($client, new Configuration()))->exchangeCode('some-code');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertStringContainsString('HTTP 400', $e->getMessage());
    }
  }


  public function testOtherClientErrorsMapToAuthFailedWithStatus(): void {
    $client = new MockHttpClient(
      [
        new MockResponse('{"error":"invalid_client"}', ['http_code' => 401]),
      ]
    );

    try {
      (new OAuthClient($client, new Configuration()))->refresh('some-token');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::AuthFailed, $e->kind);
      self::assertSame(401, $e->httpStatus);
    }
  }


  public function testNetworkErrorMapsToTransient(): void {
    // Refresh is idempotent server-side, so a dropped connection is safe to retry.
    $client = new MockHttpClient(
      function (): never {
        throw new class ('connection refused') extends \RuntimeException implements TransportExceptionInterface {
        };
      }
    );

    try {
      (new OAuthClient($client, new Configuration()))->refresh('some-token');
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::Transient, $e->kind);
      self::assertTrue($e->retryable);
    }
  }


  public function testTokenUrlCanBeOverriddenIndependently(): void {
    putenv('CA_TOKEN_URL=https://login.contaazul.com/oauth2/token');
    $capturedUrl = NULL;
    $client      = new MockHttpClient(
      function (string $method, string $url) use (&$capturedUrl) {
        $capturedUrl = $url;

        return new MockResponse('{"access_token":"a","refresh_token":"r","expires_in":3600}');
      }
    );

    (new OAuthClient($client, new Configuration()))->exchangeCode('c');

    self::assertSame('https://login.contaazul.com/oauth2/token', $capturedUrl);
  }


  public function testAuthBaseUrlIsConfigurable(): void {
    putenv('CA_AUTH_BASE_URL=https://auth.example.test');
    $capturedUrl = NULL;
    $client      = new MockHttpClient(
      function (string $method, string $url) use (&$capturedUrl) {
        $capturedUrl = $url;

        return new MockResponse('{"access_token":"a","refresh_token":"r","expires_in":3600}');
      }
    );

    (new OAuthClient($client, new Configuration()))->refresh('t');

    self::assertSame('https://auth.example.test/oauth2/token', $capturedUrl);
  }


}
