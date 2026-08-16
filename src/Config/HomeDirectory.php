<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function function_exists;
use function getenv;
use function is_array;
use function is_string;
use function posix_getpwuid;
use function posix_getuid;
use function rtrim;

/**
 * Resolves the current user's home directory across platforms.
 *
 * Windows sets neither HOME nor the posix extension, so the POSIX lookup has to
 * stay behind a function_exists guard: calling it unguarded is a fatal error on
 * that platform, and the CLI resolves a home directory on every invocation to
 * expand the default token path.
 */
final class HomeDirectory
{
  /** Returns the home directory, or null when the platform gives us nothing. */
  public static function resolve(): string|null
  {
    foreach (['HOME', 'USERPROFILE'] as $variable) {
      $value = getenv($variable);
      if (is_string($value) && $value !== '') {
        return self::normalize($value);
      }
    }

    // Windows classically splits the home directory across two variables.
    $drive = getenv('HOMEDRIVE');
    $path  = getenv('HOMEPATH');
    if (is_string($drive) && $drive !== '' && is_string($path) && $path !== '') {
      return self::normalize($drive . $path);
    }

    if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
      $entry = posix_getpwuid(posix_getuid());
      if (is_array($entry) && $entry['dir'] !== '') {
        return self::normalize($entry['dir']);
      }
    }

    return null;
  }

  /** Removes trailing separators while preserving a root path. */
  private static function normalize(string $path): string
  {
    $trimmed = rtrim($path, '/\\');

    return $trimmed === '' ? $path : $trimmed;
  }
}
