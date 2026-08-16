<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\LoginService;
use ContaAzulCli\Auth\OAuthGatewayInterface;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenRepositoryInterface;
use ContaAzulCli\Config\Configuration;
use PHPUnit\Framework\TestCase;

/** Verifies browser-login orchestration independently from AuthManager. */
final class LoginServiceTest extends TestCase
{


  /** Creates complete test configuration for the OAuth URL builder. */
  private function config(): Configuration {
    return Configuration::fromValues(
      [
        'clientId'             => 'client',
        'clientSecret'         => 'secret',
        'redirectUri'          => 'https://localhost/callback',
        'scope'                => 'sales',
        'apiBaseUrl'           => 'https://api.example.test',
        'authBaseUrl'          => 'https://auth.example.test',
        'authorizeUrl'         => 'https://auth.example.test/authorize',
        'tokenUrl'             => 'https://auth.example.test/token',
        'tokenPath'            => sys_get_temp_dir() . '/tokens.json',
        'bootstrapRefreshToken' => NULL,
        'callbackCertFile'     => NULL,
        'callbackKeyFile'      => NULL,
        'callbackTimeout'      => 30,
      ]
    );
  }


  /** Ensures the state is generated and persisted as part of the URL. */
  public function testStartBuildsAuthorizationUrlAndState(): void {
    $repo = new class implements TokenRepositoryInterface {


      public function save(TokenData $token): void {
      }


      public function load(): ?TokenData {
        return NULL;
      }


      public function delete(): void {
      }


      public function getPath(): string {
        return sys_get_temp_dir() . '/tokens.json';
      }


    };
    $oauth = new class implements OAuthGatewayInterface {


      public function exchangeCode(string $code): TokenData {
        throw new \LogicException();
      }


      public function refresh(string $refreshToken): TokenData {
        throw new \LogicException();
      }


    };

    $service = new LoginService($oauth, $repo, $this->config());
    $url     = $service->start();
    $query   = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    self::assertSame('client', $query['client_id']);
    self::assertSame('sales', $query['scope']);
    self::assertSame($service->pendingState(), $query['state']);
  }


}
