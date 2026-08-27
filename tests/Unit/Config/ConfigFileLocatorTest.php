<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\ConfigFileCandidate;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\ConfigFileStatus;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;
use function array_reverse;
use function file_put_contents;
use function getenv;
use function is_dir;
use function mkdir;
use function putenv;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ConfigFileLocatorTest extends TestCase
{
  private const array ENV_VARS = ['HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'CA_CLI_ENV_FILE'];

  /** @var array<string, string|false> */
  private array $originalEnv = [];

  /** @var list<string> */
  private array $files = [];

  /** @var list<string> */
  private array $directories = [];

  private string $home        = '';
  private string $projectRoot = '';

  protected function setUp(): void {
    foreach (self::ENV_VARS as $variable) {
      $this->originalEnv[$variable] = getenv($variable);
      putenv($variable);
    }

    $this->home        = $this->makeDirectory(sys_get_temp_dir() . '/ca-locator-home-' . uniqid());
    $this->projectRoot = $this->makeDirectory(sys_get_temp_dir() . '/ca-locator-project-' . uniqid());
    putenv('HOME=' . $this->home);
  }

  protected function tearDown(): void {
    foreach ($this->files as $file) {
      unlink($file);
    }

    // Reverse order, so a nested directory is gone before its parent.
    foreach (array_reverse($this->directories) as $directory) {
      if (! is_dir($directory)) {
        continue;
      }

      rmdir($directory);
    }

    foreach ($this->originalEnv as $variable => $value) {
      putenv($value === false ? $variable : $variable . '=' . $value);
    }
  }

  /** The project root wins over the user directory when both files exist. */
  public function testProjectRootBeatsTheUserDirectory(): void {
    $project = $this->writeEnv($this->projectRoot . '/.env');
    $this->writeUserEnv();

    $resolution = (new ConfigFileLocator($this->projectRoot, null))->locate();

    self::assertSame($project, $resolution->path());
    self::assertSame(
        [ConfigFileStatus::Unset, ConfigFileStatus::Used, ConfigFileStatus::NotReached],
        $this->statuses($resolution->candidates()),
    );
  }

  /** With no project file, the user directory is what a global install falls back to. */
  public function testFallsBackToTheUserDirectory(): void {
    $userFile = $this->writeUserEnv();

    $resolution = (new ConfigFileLocator($this->projectRoot, null))->locate();

    self::assertSame($userFile, $resolution->path());
    self::assertSame(
        [ConfigFileStatus::Unset, ConfigFileStatus::Missing, ConfigFileStatus::Used],
        $this->statuses($resolution->candidates()),
    );
  }

  /** The explicit override is evaluated before anything else. */
  public function testExplicitOverrideBeatsEveryOtherCandidate(): void {
    $override = $this->writeEnv(
        $this->makeDirectory(sys_get_temp_dir() . '/ca-locator-override-' . uniqid()) . '/custom.env',
    );
    $this->writeEnv($this->projectRoot . '/.env');
    $this->writeUserEnv();
    putenv('CA_CLI_ENV_FILE=' . $override);

    $resolution = (new ConfigFileLocator($this->projectRoot, null))->locate();

    self::assertSame($override, $resolution->path());
    self::assertSame(
        [ConfigFileStatus::Used, ConfigFileStatus::NotReached, ConfigFileStatus::NotReached],
        $this->statuses($resolution->candidates()),
    );
  }

  /**
   * An override nobody can read is a hard error.
   *
   * Skipping it would authenticate against a different account than the one
   * the operator named, and would only surface much later as a confusing 401.
   */
  public function testUnreadableOverrideIsAHardError(): void {
    putenv('CA_CLI_ENV_FILE=' . $this->projectRoot . '/nao-existe.env');

    $this->expectException(ConfigException::class);
    $this->expectExceptionMessage('CA_CLI_ENV_FILE');

    (new ConfigFileLocator($this->projectRoot, null))->locate();
  }

  /**
   * Inside a PHAR the project root is skipped outright.
   *
   * `phar://.../.env` can never exist, and reporting it as merely "missing"
   * would send an operator looking for a file they cannot create — the exact
   * confusion that made the published archives look broken.
   */
  public function testProjectRootIsSkippedInsideAPhar(): void {
    $this->writeEnv($this->projectRoot . '/.env');
    $userFile = $this->writeUserEnv();

    $resolution = (new ConfigFileLocator($this->projectRoot, '/opt/bin/ca.phar'))->locate();

    self::assertSame($userFile, $resolution->path());
    self::assertSame(
        [ConfigFileStatus::Unset, ConfigFileStatus::SkippedInPhar, ConfigFileStatus::Used],
        $this->statuses($resolution->candidates()),
    );
    self::assertNull($resolution->candidates()[1]->path);
  }

  /**
   * The current working directory is deliberately not part of the search path.
   *
   * A `cd` into any unrelated project with a `.env` would otherwise make the
   * CLI absorb that project's credentials without saying so.
   */
  public function testCurrentWorkingDirectoryIsNeverACandidate(): void {
    $resolution = (new ConfigFileLocator($this->projectRoot, null))->locate();

    self::assertSame(
        ['CA_CLI_ENV_FILE', 'raiz do projeto', 'diretório do usuário'],
        array_column($resolution->toArray(), 'origem'),
    );
  }

  /** Nothing existing anywhere is a normal state, not a failure. */
  public function testResolvesToNullWhenNoCandidateExists(): void {
    $resolution = (new ConfigFileLocator($this->projectRoot, null))->locate();

    self::assertNull($resolution->path());
    self::assertSame(
        [ConfigFileStatus::Unset, ConfigFileStatus::Missing, ConfigFileStatus::Missing],
        $this->statuses($resolution->candidates()),
    );
  }

  /** `config init` always targets the user-level file, whatever else resolved. */
  public function testUserFileIsAlwaysTheUserDirectoryOne(): void {
    $this->writeEnv($this->projectRoot . '/.env');

    self::assertSame(
        $this->home . '/.config/conta-azul-cli',
        (new ConfigFileLocator($this->projectRoot, null))->userDirectory(),
    );
    self::assertSame(
        $this->home . '/.config/conta-azul-cli/.env',
        (new ConfigFileLocator($this->projectRoot, null))->userFile(),
    );
  }

  /**
   * @param list<ConfigFileCandidate> $candidates
   *
   * @return list<ConfigFileStatus>
   */
  private function statuses(array $candidates): array {
    return array_map(
        static fn (ConfigFileCandidate $candidate): ConfigFileStatus => $candidate->status,
        $candidates,
    );
  }

  private function writeUserEnv(): string {
    $this->makeDirectory($this->home . '/.config');
    $this->makeDirectory($this->home . '/.config/conta-azul-cli');

    return $this->writeEnv($this->home . '/.config/conta-azul-cli/.env');
  }

  private function writeEnv(string $path): string {
    file_put_contents($path, 'CA_CLIENT_ID=from-' . uniqid() . "\n");
    $this->files[] = $path;

    return $path;
  }

  private function makeDirectory(string $path): string {
    if (! is_dir($path)) {
      mkdir($path, 0700, true);
    }

    $this->directories[] = $path;

    return $path;
  }
}
