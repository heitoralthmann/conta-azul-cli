<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Config\HomeDirectory;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    private const REQUIRED_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET'];
    private const OPTIONAL_VARS = ['CA_REDIRECT_URI', 'CA_SCOPE', 'CA_API_BASE_URL', 'CA_AUTH_BASE_URL', 'CA_AUTHORIZE_URL', 'CA_TOKEN_URL', 'CA_CLI_TOKEN_PATH', 'CA_BOOTSTRAP_REFRESH_TOKEN', 'CA_CALLBACK_CERT', 'CA_CALLBACK_KEY', 'CA_CALLBACK_TIMEOUT'];


    protected function setUp(): void {
        foreach (array_merge(self::REQUIRED_VARS, self::OPTIONAL_VARS) as $var) {
            $this->originalEnv[$var] = getenv($var);
            putenv($var); // unset
        }
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


    public function testMissingClientIdThrowsConfigException(): void {
        putenv('CA_CLIENT_SECRET=secret');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/CA_CLIENT_ID/');

        new Configuration();
    }


    public function testMissingClientSecretThrowsConfigException(): void {
        putenv('CA_CLIENT_ID=id');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/CA_CLIENT_SECRET/');

        new Configuration();
    }


    public function testDefaultApiBaseUrl(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertSame('https://api-v2.contaazul.com', $config->getApiBaseUrl());
    }


    public function testDefaultAuthBaseUrl(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertSame('https://auth.contaazul.com', $config->getAuthBaseUrl());
    }


    public function testCallbackTimeoutDefaultsToFiveMinutes(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        self::assertSame(300, (new Configuration())->getCallbackTimeout());
    }


    public function testCallbackTimeoutIsReadFromEnv(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CALLBACK_TIMEOUT=600');

        self::assertSame(600, (new Configuration())->getCallbackTimeout());
    }


    public function testInvalidCallbackTimeoutFallsBackToTheDefault(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CALLBACK_TIMEOUT=zero');

        self::assertSame(300, (new Configuration())->getCallbackTimeout());
    }


    public function testZeroCallbackTimeoutFallsBackToTheDefault(): void {
        // A zero-second window would make login impossible to complete.
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CALLBACK_TIMEOUT=0');

        self::assertSame(300, (new Configuration())->getCallbackTimeout());
    }


    public function testAuthorizeUrlDefaultsToTheAuthBaseUrl(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertSame('https://auth.contaazul.com/oauth2/authorize', $config->getAuthorizeUrl());
    }


    public function testAuthorizeUrlDefaultFollowsACustomAuthBaseUrl(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_AUTH_BASE_URL=https://auth.example.test/');

        $config = new Configuration();

        self::assertSame('https://auth.example.test/oauth2/authorize', $config->getAuthorizeUrl());
    }


    public function testAuthorizeUrlCanBeOverriddenWholesale(): void {
        // Production apps authorize on a different host and path than they
        // exchange tokens on, so the whole URL has to be replaceable.
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_AUTHORIZE_URL=https://login.contaazul.com/#/oauth/authorize');

        $config = new Configuration();

        self::assertSame('https://login.contaazul.com/#/oauth/authorize', $config->getAuthorizeUrl());
        self::assertSame('https://auth.contaazul.com', $config->getAuthBaseUrl(), 'Token endpoint must stay independent.');
    }


    public function testTildeInTokenPathIsExpanded(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CLI_TOKEN_PATH=~/my-tokens.json');
        $home = HomeDirectory::resolve();

        $config = new Configuration();

        self::assertSame($home . '/my-tokens.json', $config->getTokenPath());
    }


    public function testBootstrapRefreshTokenIsNullWhenNotSet(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertNull($config->getBootstrapRefreshToken());
    }


    public function testBootstrapRefreshTokenIsReadFromEnv(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_BOOTSTRAP_REFRESH_TOKEN=my-refresh-token');

        $config = new Configuration();

        self::assertSame('my-refresh-token', $config->getBootstrapRefreshToken());
    }


    public function testScopeIsNullWhenNotSet(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertNull($config->getScope());
    }


    public function testScopeIsReadFromEnv(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_SCOPE=sales');

        $config = new Configuration();

        self::assertSame('sales', $config->getScope());
    }


    public function testCallbackCertAndKeyAreNullWhenNotSet(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertNull($config->getCallbackCertFile());
        self::assertNull($config->getCallbackKeyFile());
    }


    public function testCallbackCertAndKeyAreReadFromEnv(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CALLBACK_CERT=/tmp/cert.pem');
        putenv('CA_CALLBACK_KEY=/tmp/key.pem');

        $config = new Configuration();

        self::assertSame('/tmp/cert.pem', $config->getCallbackCertFile());
        self::assertSame('/tmp/key.pem', $config->getCallbackKeyFile());
    }


    public function testCallbackCertTildeIsExpanded(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CALLBACK_CERT=~/.certs/cert.pem');
        $home = HomeDirectory::resolve();

        $config = new Configuration();

        self::assertSame($home . '/.certs/cert.pem', $config->getCallbackCertFile());
    }


    public function testApiBaseUrlTrailingSlashIsStripped(): void {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_API_BASE_URL=https://api.example.com/');

        $config = new Configuration();

        self::assertSame('https://api.example.com', $config->getApiBaseUrl());
    }


}
