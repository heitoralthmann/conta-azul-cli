<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\Configuration;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    private const REQUIRED_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET'];
    private const OPTIONAL_VARS = ['CA_REDIRECT_URI', 'CA_API_BASE_URL', 'CA_AUTH_BASE_URL', 'CA_CLI_TOKEN_PATH', 'CA_BOOTSTRAP_REFRESH_TOKEN'];

    protected function setUp(): void
    {
        foreach (array_merge(self::REQUIRED_VARS, self::OPTIONAL_VARS) as $var) {
            $this->originalEnv[$var] = getenv($var);
            putenv($var); // unset
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $var => $value) {
            if ($value === false) {
                putenv($var);
            } else {
                putenv("{$var}={$value}");
            }
        }
    }

    public function testMissingClientIdThrowsConfigException(): void
    {
        putenv('CA_CLIENT_SECRET=secret');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/CA_CLIENT_ID/');

        new Configuration();
    }

    public function testMissingClientSecretThrowsConfigException(): void
    {
        putenv('CA_CLIENT_ID=id');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/CA_CLIENT_SECRET/');

        new Configuration();
    }

    public function testDefaultApiBaseUrl(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertSame('https://api-v2.contaazul.com', $config->getApiBaseUrl());
    }

    public function testDefaultAuthBaseUrl(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertSame('https://auth.contaazul.com', $config->getAuthBaseUrl());
    }

    public function testTildeInTokenPathIsExpanded(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_CLI_TOKEN_PATH=~/my-tokens.json');
        $home = getenv('HOME') ?: '/tmp';

        $config = new Configuration();

        self::assertSame($home . '/my-tokens.json', $config->getTokenPath());
    }

    public function testBootstrapRefreshTokenIsNullWhenNotSet(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');

        $config = new Configuration();

        self::assertNull($config->getBootstrapRefreshToken());
    }

    public function testBootstrapRefreshTokenIsReadFromEnv(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_BOOTSTRAP_REFRESH_TOKEN=my-refresh-token');

        $config = new Configuration();

        self::assertSame('my-refresh-token', $config->getBootstrapRefreshToken());
    }

    public function testApiBaseUrlTrailingSlashIsStripped(): void
    {
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_API_BASE_URL=https://api.example.com/');

        $config = new Configuration();

        self::assertSame('https://api.example.com', $config->getApiBaseUrl());
    }
}
