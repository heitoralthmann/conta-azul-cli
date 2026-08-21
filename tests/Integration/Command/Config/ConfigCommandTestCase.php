<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Config;

use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use ContaAzulCli\Tests\Support\DecodedPayloads;
use ContaAzulCli\Tests\Support\PosixPermissions;
use ContaAzulCli\Tests\Support\TemporaryDirectories;

use function file_put_contents;
use function getenv;
use function mkdir;
use function putenv;

/**
 * Shared fixture for the config command tests.
 *
 * Every case runs against a throwaway home directory and a throwaway project
 * root, so the developer's own `.env` and `~/.config/conta-azul-cli/` are never
 * read or written by the suite.
 */
abstract class ConfigCommandTestCase extends CommandTestCase
{
  use DecodedPayloads;
  use PosixPermissions;
  use TemporaryDirectories;

  private const array ENV_VARS = ['HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'CA_CLI_ENV_FILE'];

  /** @var array<string, string|false> */
  private array $originalEnv = [];

  protected string $home        = '';
  protected string $projectRoot = '';

  protected function setUp(): void {
    foreach (self::ENV_VARS as $variable) {
      $this->originalEnv[$variable] = getenv($variable);
      putenv($variable);
    }

    $this->home        = $this->makeTemporaryDirectory('ca-config-home');
    $this->projectRoot = $this->makeTemporaryDirectory('ca-config-project');
    putenv('HOME=' . $this->home);
  }

  protected function tearDown(): void {
    $this->removeTemporaryDirectories();

    foreach ($this->originalEnv as $variable => $value) {
      putenv($value === false ? $variable : $variable . '=' . $value);
    }
  }

  /** Builds a locator bound to this test's throwaway directories. */
  protected function locator(string|null $pharPath = null): ConfigFileLocator {
    return new ConfigFileLocator($this->projectRoot, $pharPath);
  }

  /** Writes the project-root file, which is the candidate that normally wins. */
  protected function writeProjectEnv(string $contents): string {
    return $this->writeFile($this->projectRoot . '/.env', $contents);
  }

  /** Writes the user-level file, the one `config init` targets. */
  protected function writeUserEnv(string $contents): string {
    mkdir($this->userDirectory(), 0700, true);

    return $this->writeFile($this->userDirectory() . '/.env', $contents);
  }

  /** Returns the user-level directory, whether or not it exists yet. */
  protected function userDirectory(): string {
    return $this->home . '/.config/conta-azul-cli';
  }

  protected function writeFile(string $path, string $contents): string {
    file_put_contents($path, $contents);

    return $path;
  }
}
