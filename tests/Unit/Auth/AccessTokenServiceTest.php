<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\AccessTokenService;
use ContaAzulCli\Auth\OAuthGatewayInterface;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenLockInterface;
use ContaAzulCli\Auth\TokenRepositoryInterface;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

/** Verifies token lifecycle policy independently from the facade and adapters. */
final class AccessTokenServiceTest extends TestCase
{
  /** Returns the minimum complete configuration used by service tests. */
  private function config(string|null $bootstrap = null): Configuration {
    return Configuration::fromValues(
        [
          'clientId'             => 'client',
          'clientSecret'         => 'secret',
          'redirectUri'          => 'https://localhost/callback',
          'scope'                => null,
          'apiBaseUrl'           => 'https://api.example.test',
          'authBaseUrl'          => 'https://auth.example.test',
          'authorizeUrl'         => 'https://auth.example.test/authorize',
          'tokenUrl'             => 'https://auth.example.test/token',
          'tokenPath'            => sys_get_temp_dir() . '/tokens.json',
          'bootstrapRefreshToken' => $bootstrap,
          'callbackCertFile'     => null,
          'callbackKeyFile'      => null,
          'callbackTimeout'      => 30,
        ],
    );
  }

  /** Ensures an expired token is refreshed and rotated through its interfaces. */
  public function testRefreshesAndPersistsAnExpiringToken(): void {
    $stored = new TokenData(
        'old-access',
        new DateTimeImmutable('-1 minute'),
        'old-refresh',
        new DateTimeImmutable('-1 hour'),
    );
    $fresh  = new TokenData(
        'new-access',
        new DateTimeImmutable('+1 hour'),
        'new-refresh',
        new DateTimeImmutable('now'),
    );
    $repo   = new class ($stored) implements TokenRepositoryInterface {
      public TokenData|null $saved = null;

      public function __construct(private TokenData|null $token) {
      }

      public function save(TokenData $token): void {
        $this->saved = $token;
        $this->token = $token;
      }

      public function load(): TokenData|null {
        return $this->token;
      }

      public function delete(): void {
        $this->token = null;
      }

      public function getPath(): string {
        return sys_get_temp_dir() . '/tokens.json';
      }
    };
    $oauth  = new class ($fresh) implements OAuthGatewayInterface {
      public function __construct(private TokenData $fresh) {
      }

      public function exchangeCode(string $code): TokenData {
        return $this->fresh;
      }

      public function refresh(string $refreshToken): TokenData {
        return $this->fresh;
      }
    };
    $lock   = new class implements TokenLockInterface {
      public function synchronized(callable $operation): mixed {
        return $operation();
      }
    };

    $service = new AccessTokenService($repo, $oauth, $this->config(), $lock);

    self::assertSame('new-access', $service->getValidAccessToken());
    self::assertSame('new-refresh', $repo->saved?->refreshToken);
  }

  /** Ensures missing credentials retain the stable authentication error. */
  public function testMissingTokenIsAuthFailed(): void {
    $repo  = new class implements TokenRepositoryInterface {
      public function save(TokenData $token): void {
      }

      public function load(): TokenData|null {
        return null;
      }

      public function delete(): void {
      }

      public function getPath(): string {
        return sys_get_temp_dir() . '/tokens.json';
      }
    };
    $oauth = new class implements OAuthGatewayInterface {
      public function exchangeCode(string $code): TokenData {
        throw new LogicException();
      }

      public function refresh(string $refreshToken): TokenData {
        throw new LogicException();
      }
    };
    $lock  = new class implements TokenLockInterface {
      public function synchronized(callable $operation): mixed {
        return $operation();
      }
    };

    try {
      (new AccessTokenService($repo, $oauth, $this->config(), $lock))->getValidAccessToken();
      self::fail('Expected CliException');
    } catch (CliException $exception) {
      self::assertSame(ErrorKind::AuthFailed, $exception->kind);
    }
  }
}
