<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use Phar;

use function dirname;
use function getenv;
use function is_file;
use function is_readable;
use function is_string;

/**
 * Decides which `.env` file the CLI reads, so credentials can live outside the
 * project directory and a globally installed PHAR still finds them.
 *
 * The rule is deliberately one sentence long: **the first candidate that
 * exists wins, and nothing is merged.** Merging would make `ca config set`
 * ambiguous (which file did it write?) and would turn a stale leftover file
 * into a silent partial override. `ca config path` prints the whole search
 * path, so the decision is always auditable.
 */
final class ConfigFileLocator
{
  /** Explicit per-invocation escape hatch, evaluated before anything else. */
  public const string OVERRIDE_VARIABLE = 'CA_CLI_ENV_FILE';

  /** Directory, under the user's home, that already holds tokens, certs, and the log. */
  public const string USER_DIRECTORY = '/.config/conta-azul-cli';

  /**
   * Binds the locator to a project root and a PHAR context.
   *
   * @param string      $projectRoot Directory whose `.env` is candidate 2.
   * @param string|null $pharPath    Archive currently executing, or null outside one.
   */
  public function __construct(
      private readonly string $projectRoot,
      private readonly string|null $pharPath,
  ) {
  }

  /** Builds a locator for the running process, PHAR or not. */
  public static function forRuntime(): self {
    $phar = Phar::running(false);

    return new self(dirname(__DIR__, 2), $phar === '' ? null : $phar);
  }

  /**
   * Walks the search path once and reports both the winner and the trail.
   *
   * @throws ConfigException When the explicit override points at a file that
   *                         cannot be read.
   */
  public function locate(): ConfigFileResolution {
    $candidates = [];
    $resolved   = null;

    foreach ($this->searchPath() as $entry) {
      $path = $entry->path;
      if ($path === null) {
        // Nothing to test: the entry already carries its final verdict.
        $candidates[] = $entry;

        continue;
      }

      if ($resolved !== null) {
        $candidates[] = new ConfigFileCandidate($entry->origin, $path, ConfigFileStatus::NotReached);

        continue;
      }

      if (! is_file($path) || ! is_readable($path)) {
        $candidates[] = $entry;

        continue;
      }

      $resolved     = $path;
      $candidates[] = new ConfigFileCandidate($entry->origin, $path, ConfigFileStatus::Used);
    }

    return new ConfigFileResolution($resolved, $candidates);
  }

  /**
   * Returns the user-level directory that holds `.env`, tokens, and certs.
   *
   * Null means the platform gave us no home directory at all.
   */
  public function userDirectory(): string|null {
    $home = HomeDirectory::resolve();

    return $home === null ? null : $home . self::USER_DIRECTORY;
  }

  /**
   * Returns the user-level file, which `ca config init` always targets.
   *
   * Null means the platform gave us no home directory at all.
   */
  public function userFile(): string|null {
    $directory = $this->userDirectory();

    return $directory === null ? null : $directory . '/.env';
  }

  /**
   * Builds the ordered search path.
   *
   * Entries that carry a path start out as {@see ConfigFileStatus::Missing} —
   * not found until proven otherwise. {@see self::locate()} is the only place
   * that knows whether an earlier candidate already won, so it owns the
   * upgrade to `Used` or `NotReached`.
   *
   * @return list<ConfigFileCandidate>
   *
   * @throws ConfigException When the explicit override cannot be read.
   */
  private function searchPath(): array {
    return [
      $this->overrideEntry(),
      $this->projectEntry(),
      $this->userEntry(),
    ];
  }

  /**
   * Candidate 1: whatever `CA_CLI_ENV_FILE` names.
   *
   * An override that cannot be read is a hard error, never a silent skip:
   * falling through to the next candidate would authenticate against a
   * different account than the operator asked for, and the mistake would
   * surface much later as a confusing `401`.
   *
   * @throws ConfigException When the override is set but unreadable.
   */
  private function overrideEntry(): ConfigFileCandidate {
    $override = getenv(self::OVERRIDE_VARIABLE);
    if (! is_string($override) || $override === '') {
      return new ConfigFileCandidate(self::OVERRIDE_VARIABLE, null, ConfigFileStatus::Unset);
    }

    if (! is_file($override) || ! is_readable($override)) {
      throw new ConfigException(
          self::OVERRIDE_VARIABLE . ' aponta para um arquivo que não pode ser lido: ' . $override
              . '. Corrija o caminho ou remova a variável.',
      );
    }

    return new ConfigFileCandidate(self::OVERRIDE_VARIABLE, $override, ConfigFileStatus::Missing);
  }

  /**
   * Candidate 2: the repository checkout's own `.env`.
   *
   * Inside a PHAR this resolves to `phar://.../.env`, which can never exist —
   * the whole reason the published archives silently degraded to four
   * commands. Skipping it there keeps `ca config path` honest instead of
   * reporting a path nobody can create.
   *
   * Deliberately absent from this search path: `getcwd() . '/.env'`. Running
   * the CLI from inside any unrelated project that happens to have a `.env`
   * would make it absorb that project's variables — third-party credentials,
   * silently. `CA_CLI_ENV_FILE` covers the same need explicitly, per
   * invocation, and shows up in `ca config path`.
   */
  private function projectEntry(): ConfigFileCandidate {
    if ($this->pharPath !== null) {
      return new ConfigFileCandidate('raiz do projeto', null, ConfigFileStatus::SkippedInPhar);
    }

    return new ConfigFileCandidate('raiz do projeto', $this->projectRoot . '/.env', ConfigFileStatus::Missing);
  }

  /** Candidate 3: the user-level file, the one a global install relies on. */
  private function userEntry(): ConfigFileCandidate {
    $userFile = $this->userFile();
    if ($userFile === null) {
      return new ConfigFileCandidate('diretório do usuário', null, ConfigFileStatus::SkippedNoHome);
    }

    return new ConfigFileCandidate('diretório do usuário', $userFile, ConfigFileStatus::Missing);
  }
}
