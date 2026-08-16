<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Config\Configuration;
use PHPUnit\Framework\TestCase;

final class TokenStoreTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];
  private string $tokenPath;

  private const ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_CLI_TOKEN_PATH'];


  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-test-' . uniqid() . '/tokens.json';
    putenv('CA_CLIENT_ID=id');
    putenv('CA_CLIENT_SECRET=secret');
    putenv("CA_CLI_TOKEN_PATH={$this->tokenPath}");
  }


  protected function tearDown(): void {
    if (file_exists($this->tokenPath)) {
      unlink($this->tokenPath);
    }
    $dir = dirname($this->tokenPath);
    if (is_dir($dir)) {
      array_map('unlink', glob($dir . '/*') ?: []);
      rmdir($dir);
    }

    foreach ($this->originalEnv as $var => $value) {
      if ($value === FALSE) {
        putenv($var);
      } else {
        putenv("{$var}={$value}");
      }
    }
  }


  private function store(): TokenStore {
    return new TokenStore(new Configuration());
  }


  private function sampleToken(): TokenData {
    return new TokenData(
      accessToken: 'access-abc',
      accessTokenExpiresAt: new \DateTimeImmutable('2026-05-29T18:00:00+00:00'),
      refreshToken: 'refresh-xyz',
      refreshTokenObtainedAt: new \DateTimeImmutable('2026-05-29T17:00:00+00:00'),
    );
  }


  public function testSaveThenLoadRoundTrips(): void {
    $store = $this->store();
    $store->save($this->sampleToken());

    $loaded = $store->load();

    self::assertNotNull($loaded);
    self::assertSame('access-abc', $loaded->accessToken);
    self::assertSame('refresh-xyz', $loaded->refreshToken);
    self::assertSame('Bearer', $loaded->tokenType);
    self::assertSame(
      '2026-05-29T18:00:00+00:00',
      $loaded->accessTokenExpiresAt->format(\DateTimeInterface::ATOM),
    );
  }


  public function testSaveCreatesTheDirectoryWhenMissing(): void {
    self::assertDirectoryDoesNotExist(dirname($this->tokenPath));

    $this->store()->save($this->sampleToken());

    self::assertFileExists($this->tokenPath);
  }


  public function testSavedFileIsNotReadableByOtherUsers(): void {
    if (DIRECTORY_SEPARATOR === '\\') {
      self::markTestSkipped('Windows has no POSIX permission bits; chmod only toggles read-only there.');
    }

    $this->store()->save($this->sampleToken());

    $mode = fileperms($this->tokenPath) & 0777;

    self::assertSame(0600, $mode, 'Token file must be 0600 — it holds credentials.');
  }


  public function testLoadReturnsNullWhenFileIsAbsent(): void {
    self::assertNull($this->store()->load());
  }


  public function testLoadReturnsNullOnCorruptJson(): void {
    mkdir(dirname($this->tokenPath), 0700, TRUE);
    file_put_contents($this->tokenPath, '{not valid json');

    self::assertNull($this->store()->load());
  }


  public function testLoadReturnsNullOnEmptyFile(): void {
    mkdir(dirname($this->tokenPath), 0700, TRUE);
    file_put_contents($this->tokenPath, '');

    self::assertNull($this->store()->load());
  }


  public function testDeleteRemovesTheFile(): void {
    $store = $this->store();
    $store->save($this->sampleToken());

    $store->delete();

    self::assertFileDoesNotExist($this->tokenPath);
    self::assertNull($store->load());
  }


  public function testDeleteIsANoopWhenFileIsAbsent(): void {
    $this->store()->delete();

    self::assertFileDoesNotExist($this->tokenPath);
  }


}
