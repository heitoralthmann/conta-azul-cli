<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Config\EnvironmentConfigurationLoader;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

final class EnvironmentConfigurationLoaderTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private const array ENVIRONMENT_VARIABLES = [
    'CA_CLIENT_ID',
    'CA_CLIENT_SECRET',
    'CA_REDIRECT_URI',
    'CA_SCOPE',
    'CA_API_BASE_URL',
    'CA_AUTH_BASE_URL',
    'CA_AUTHORIZE_URL',
    'CA_TOKEN_URL',
    'CA_CLI_TOKEN_PATH',
    'CA_BOOTSTRAP_REFRESH_TOKEN',
    'CA_CALLBACK_CERT',
    'CA_CALLBACK_KEY',
    'CA_CALLBACK_TIMEOUT',
  ];

  protected function setUp(): void {
    foreach (self::ENVIRONMENT_VARIABLES as $variable) {
      $this->originalEnv[$variable] = getenv($variable);
      putenv($variable);
    }
  }

  protected function tearDown(): void {
    foreach ($this->originalEnv as $variable => $value) {
      putenv($value === false ? $variable : $variable . '=' . $value);
    }
  }

  public function testLoadsEnvironmentIntoConfigurationValueObject(): void {
    putenv('CA_CLIENT_ID=client-id');
    putenv('CA_CLIENT_SECRET=client-secret');
    putenv('CA_SCOPE=finance');

    $configuration = (new EnvironmentConfigurationLoader())->load();

    self::assertInstanceOf(Configuration::class, $configuration);
    self::assertSame('client-id', $configuration->getClientId());
    self::assertSame('client-secret', $configuration->getClientSecret());
    self::assertSame('finance', $configuration->getScope());
  }

  public function testConfigurationCanBeCreatedWithoutEnvironmentAccess(): void {
    $configuration = Configuration::fromValues(
        [
          'clientId' => 'client-id',
          'clientSecret' => 'client-secret',
          'redirectUri' => 'https://example.test/callback',
          'scope' => null,
          'apiBaseUrl' => 'https://api.example.test',
          'authBaseUrl' => 'https://auth.example.test',
          'authorizeUrl' => 'https://auth.example.test/authorize',
          'tokenUrl' => 'https://auth.example.test/token',
          'tokenPath' => '/tmp/tokens.json',
          'bootstrapRefreshToken' => null,
          'callbackCertFile' => null,
          'callbackKeyFile' => null,
          'callbackTimeout' => 30,
        ],
    );

    self::assertSame('https://api.example.test', $configuration->getApiBaseUrl());
    self::assertSame(30, $configuration->getCallbackTimeout());
  }
}
