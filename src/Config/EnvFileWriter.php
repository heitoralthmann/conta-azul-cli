<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function array_pop;
use function chmod;
use function dirname;
use function end;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function trim;

use const LOCK_EX;

/**
 * Creates and edits `.env` files without disturbing what is already in them.
 *
 * The file is hand-edited far more often than it is written by the CLI, so an
 * upsert has to keep comments, blank lines, and ordering exactly as they were:
 * rewriting the file from parsed key/value pairs would silently delete the
 * guidance an operator relies on.
 *
 * Permissions match {@see \ContaAzulCli\Auth\TokenStore}: `0700` on the
 * directory, `0600` on the file, reapplied on every write.
 */
final class EnvFileWriter
{
  /** Characters that need no quoting at all in an env file value. */
  private const string BARE_VALUE_PATTERN = '/^[A-Za-z0-9_.\/:@+-]+$/';

  /**
   * Writes a brand-new file, creating its directory when needed.
   *
   * @throws ConfigException When the directory or the file cannot be written.
   */
  public function create(string $path, string $contents): void {
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
      throw new ConfigException('Não foi possível criar o diretório ' . $directory . '.');
    }

    $this->write($path, $contents);
  }

  /**
   * Inserts or replaces one assignment, leaving everything else untouched.
   *
   * @throws ConfigException When the file cannot be read or written.
   */
  public function upsert(string $path, string $name, string $value): void {
    if (! file_exists($path)) {
      throw new ConfigException('Arquivo de configuração não encontrado: ' . $path . '.');
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
      throw new ConfigException('Não foi possível ler ' . $path . '.');
    }

    $this->write($path, $this->replaceAssignment($contents, $name, $value));
  }

  /**
   * Rewrites the file body with one assignment updated or appended.
   *
   * A duplicate assignment further down the file would still win at load
   * time, so the first one is replaced in place and any later one is dropped.
   */
  private function replaceAssignment(string $contents, string $name, string $value): string {
    $pattern  = '/^[ \t]*(?:export[ \t]+)?' . preg_quote($name, '/') . '[ \t]*=/';
    $rendered = $name . '=' . $this->renderValue($value);
    $lines    = [];
    $written  = false;

    foreach (explode("\n", $contents) as $line) {
      if (preg_match($pattern, $line) !== 1) {
        $lines[] = $line;

        continue;
      }

      if ($written) {
        continue;
      }

      $lines[] = $rendered;
      $written = true;
    }

    if (! $written) {
      // Normalize the tail to exactly one blank line, so an appended variable
      // reads as its own entry instead of looking like it belongs to whatever
      // comment happened to end the file.
      while ($lines !== [] && trim((string) end($lines)) === '') {
        array_pop($lines);
      }

      if ($lines !== []) {
        $lines[] = '';
      }

      $lines[] = $rendered;
    }

    $body = implode("\n", $lines);

    return str_ends_with($body, "\n") ? $body : $body . "\n";
  }

  /**
   * Quotes a value so Symfony's Dotenv reads back exactly what was written.
   *
   * Single quotes are preferred: inside them Dotenv treats `$` as literal and
   * gives backslashes no special meaning, so nothing needs escaping. They
   * cannot carry a single quote themselves, which is the only case that falls
   * through to double quotes, where `\`, `"`, and `$` all have to be escaped.
   */
  private function renderValue(string $value): string {
    if ($value === '') {
      return '';
    }

    if (preg_match(self::BARE_VALUE_PATTERN, $value) === 1) {
      return $value;
    }

    if (! str_contains($value, "'")) {
      return "'" . $value . "'";
    }

    return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
  }

  /**
   * Writes the body and reapplies restrictive permissions.
   *
   * @throws ConfigException When the file cannot be written.
   */
  private function write(string $path, string $contents): void {
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
      throw new ConfigException('Não foi possível escrever em ' . $path . '.');
    }

    // No-op on Windows, where PHP's chmod only toggles the read-only flag:
    // the file carries credentials, so on that platform it relies on the
    // user profile directory's own ACLs.
    chmod($path, 0600);
  }
}
