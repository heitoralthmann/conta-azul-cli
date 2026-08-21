<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Support;

use function is_dir;
use function is_string;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Throwaway directory roots for tests that exercise real filesystem behavior.
 *
 * The configuration layer's contract is largely about paths and permissions,
 * so mocking the filesystem would test the mock. These helpers make the real
 * thing cheap: each test gets its own root and drops it whole afterwards,
 * rather than bookkeeping every file and nested directory it created.
 */
trait TemporaryDirectories
{
  /** @var list<string> */
  private array $temporaryRoots = [];

  /** Creates a private directory that will be removed with everything under it. */
  protected function makeTemporaryDirectory(string $prefix): string {
    $path = sys_get_temp_dir() . '/' . $prefix . '-' . uniqid();
    mkdir($path, 0700, true);
    $this->temporaryRoots[] = $path;

    return $path;
  }

  /** Removes every root created by this test, and everything inside them. */
  protected function removeTemporaryDirectories(): void {
    foreach ($this->temporaryRoots as $root) {
      self::removeTree($root);
    }

    $this->temporaryRoots = [];
  }

  private static function removeTree(string $path): void {
    if (! is_dir($path)) {
      return;
    }

    foreach ((array) scandir($path) as $entry) {
      if (! is_string($entry) || $entry === '.' || $entry === '..') {
        continue;
      }

      $child = $path . '/' . $entry;
      if (is_dir($child)) {
        self::removeTree($child);

        continue;
      }

      unlink($child);
    }

    rmdir($path);
  }
}
