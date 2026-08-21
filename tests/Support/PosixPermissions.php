<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Support;

use function decoct;
use function fileperms;

use const DIRECTORY_SEPARATOR;

/**
 * Permission-bit assertions that stay honest on Windows.
 *
 * The production code is right on every platform: it always calls `chmod`. But
 * on Windows that call only toggles the read-only flag — the behavior already
 * documented at {@see \ContaAzulCli\Auth\TokenStore::save()} — so
 * `fileperms() & 0777` never reads back as `0600` or `0700` there, and files
 * carrying credentials rely on the user profile directory's own ACLs instead.
 *
 * The assertions are what have to adapt. A test that exists only to check
 * permissions calls {@see self::requirePosixPermissions()} and skips whole;
 * one that checks permissions alongside real behavior uses
 * {@see self::assertPermissions()}, which stands down on Windows and leaves
 * the rest of the case running.
 */
trait PosixPermissions
{
  /** Whether this platform records POSIX permission bits at all. */
  protected static function hasPosixPermissions(): bool {
    return DIRECTORY_SEPARATOR !== '\\';
  }

  /** Skips a case whose only subject is permission bits. */
  protected static function requirePosixPermissions(): void {
    if (self::hasPosixPermissions()) {
      return;
    }

    self::markTestSkipped('Windows has no POSIX permission bits; chmod only toggles read-only there.');
  }

  /**
   * Asserts a path's permission bits where the platform keeps them.
   *
   * @param string $expected Octal digits, as `decoct()` renders them: `600`, `700`.
   */
  protected static function assertPermissions(string $expected, string $path): void {
    if (! self::hasPosixPermissions()) {
      return;
    }

    self::assertSame($expected, decoct((int) fileperms($path) & 0777), $path);
  }
}
