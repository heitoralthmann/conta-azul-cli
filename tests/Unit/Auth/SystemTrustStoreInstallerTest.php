<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\SystemTrustStoreInstaller;
use ContaAzulCli\Error\CliException;
use PHPUnit\Framework\TestCase;

use function count;
use function getenv;
use function putenv;

final class SystemTrustStoreInstallerTest extends TestCase
{
  private string|false $originalHome = false;

  protected function setUp(): void {
    $this->originalHome = getenv('HOME');
    putenv('HOME=/Users/operator');
  }

  protected function tearDown(): void {
    putenv($this->originalHome === false ? 'HOME' : 'HOME=' . $this->originalHome);
  }

  public function testSkipsInstallWhenTheCaIsAlreadyTrusted(): void {
    $ran       = [];
    $installer = new SystemTrustStoreInstaller(
        'Darwin',
        static function (array $command) use (&$ran): int {
          $ran[] = $command;

          return 0;
        },
    );

    $installer->ensureTrusted('/tmp/ca.pem');

    self::assertCount(1, $ran);
    self::assertSame(['/usr/bin/security', 'verify-cert', '-c', '/tmp/ca.pem'], $ran[0]);
  }

  public function testInstallsOnDarwinWhenVerifyFailsThenSucceeds(): void {
    $ran       = [];
    $installer = new SystemTrustStoreInstaller(
        'Darwin',
        static function (array $command) use (&$ran): int {
          $ran[] = $command;

          return $command[1] === 'verify-cert' && $ran === [['/usr/bin/security', 'verify-cert', '-c', '/tmp/ca.pem']]
            ? 1
            : 0;
        },
    );

    $installer->ensureTrusted('/tmp/ca.pem');

    self::assertSame(
        [
          ['/usr/bin/security', 'verify-cert', '-c', '/tmp/ca.pem'],
          [
            '/usr/bin/security',
            'add-trusted-cert',
            '-r',
            'trustRoot',
            '-k',
            '/Users/operator/Library/Keychains/login.keychain-db',
            '/tmp/ca.pem',
          ],
          ['/usr/bin/security', 'verify-cert', '-c', '/tmp/ca.pem'],
        ],
        $ran,
    );
  }

  public function testUsesCertutilOnWindows(): void {
    $ran       = [];
    $installer = new SystemTrustStoreInstaller(
        'Windows',
        static function (array $command) use (&$ran): int {
          $ran[] = $command;

          return $command[2] === '-verify' && $command === $ran[0] && count($ran) === 1 ? 1 : 0;
        },
    );

    $installer->ensureTrusted('C:\\ca.pem');

    self::assertSame('certutil', $ran[0][0]);
    self::assertSame('-verify', $ran[0][2]);
    self::assertSame('-addstore', $ran[1][2]);
    self::assertSame('Root', $ran[1][3]);
  }

  public function testUnknownPlatformExplainsHowToTrustTheCaByHand(): void {
    $installer = new SystemTrustStoreInstaller('NetBSD', static fn (array $command): int => 0);

    $this->expectException(CliException::class);
    $this->expectExceptionMessageMatches('/Confie manualmente/');

    $installer->ensureTrusted('/tmp/ca.pem');
  }

  public function testThrowsWhenInstallDoesNotMakeTheCaTrusted(): void {
    $installer = new SystemTrustStoreInstaller('Darwin', static fn (array $command): int => 1);

    $this->expectException(CliException::class);
    $this->expectExceptionMessageMatches('/Não foi possível instalar a autoridade certificadora/');

    $installer->ensureTrusted('/tmp/ca.pem');
  }
}
