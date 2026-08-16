<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\HomeDirectory;
use PHPUnit\Framework\TestCase;

final class HomeDirectoryTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private const ENV_VARS = ['HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH'];


  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
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


  public function testPrefersHome(): void {
    putenv('HOME=/home/heitor');
    putenv('USERPROFILE=C:\Users\heitor');

    self::assertSame('/home/heitor', HomeDirectory::resolve());
  }


  public function testFallsBackToUserProfileOnWindows(): void {
    // Windows sets USERPROFILE and leaves HOME unset.
    putenv('USERPROFILE=C:\Users\heitor');

    self::assertSame('C:\Users\heitor', HomeDirectory::resolve());
  }


  public function testFallsBackToHomeDriveAndHomePath(): void {
    putenv('HOMEDRIVE=C:');
    putenv('HOMEPATH=\Users\heitor');

    self::assertSame('C:\Users\heitor', HomeDirectory::resolve());
  }


  public function testIgnoresEmptyValues(): void {
    putenv('HOME=');
    putenv('USERPROFILE=C:\Users\heitor');

    self::assertSame('C:\Users\heitor', HomeDirectory::resolve());
  }


  public function testStripsTrailingSeparators(): void {
    putenv('HOME=/home/heitor/');

    self::assertSame('/home/heitor', HomeDirectory::resolve());
  }


  public function testTrailingSeparatorOnlyPathIsPreserved(): void {
    putenv('HOME=/');

    self::assertSame('/', HomeDirectory::resolve());
  }


  public function testResolvesOnTheCurrentPlatformWithoutEnvHints(): void {
    // With every variable cleared, POSIX platforms still resolve via passwd;
    // the guard is what keeps this from being fatal on Windows.
    $resolved = HomeDirectory::resolve();

    if (function_exists('posix_getpwuid')) {
      self::assertNotNull($resolved);
    } else {
      self::assertNull($resolved);
    }
  }


}
