<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\CallbackCertificateProvisioner;
use ContaAzulCli\Auth\TrustStoreInstaller;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Tests\Support\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;

final class CallbackCertificateProvisionerTest extends TestCase
{
  use TemporaryDirectories;

  protected function tearDown(): void {
    $this->removeTemporaryDirectories();
  }

  public function testHttpRedirectSkipsTlsAndDoesNotWriteFiles(): void {
    $directory   = $this->makeTemporaryDirectory('ca-cli-certs');
    $trustStore  = $this->recordingTrustStore();
    $provisioner = new CallbackCertificateProvisioner($directory, $trustStore);

    $material = $provisioner->ensure($this->config(['redirectUri' => 'http://localhost:9876/callback']));

    self::assertFalse($material->usesTls());
    self::assertNull($material->certFile);
    self::assertSame([], $trustStore->paths);
    self::assertFileDoesNotExist($directory . '/cert.pem');
  }

  public function testHttpsRedirectIssuesCertsAndTrustsTheCa(): void {
    $directory   = $this->makeTemporaryDirectory('ca-cli-certs');
    $trustStore  = $this->recordingTrustStore();
    $provisioner = new CallbackCertificateProvisioner($directory, $trustStore);

    $material = $provisioner->ensure($this->config());

    self::assertTrue($material->usesTls());
    self::assertSame($directory . '/cert.pem', $material->certFile);
    self::assertSame($directory . '/key.pem', $material->keyFile);
    self::assertSame([$directory . '/ca.pem'], $trustStore->paths);
  }

  public function testExplicitReadableCertsWinAndSkipTheLocalCa(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $cert      = $directory . '/custom-cert.pem';
    $key       = $directory . '/custom-key.pem';
    file_put_contents($cert, 'placeholder-cert');
    file_put_contents($key, 'placeholder-key');

    $autoDir     = $this->makeTemporaryDirectory('ca-cli-auto');
    $trustStore  = $this->recordingTrustStore();
    $provisioner = new CallbackCertificateProvisioner($autoDir, $trustStore);

    $material = $provisioner->ensure($this->config([
      'callbackCertFile' => $cert,
      'callbackKeyFile'  => $key,
    ]));

    self::assertSame($cert, $material->certFile);
    self::assertSame($key, $material->keyFile);
    self::assertNull($material->caFile);
    self::assertSame([], $trustStore->paths);
    self::assertFileDoesNotExist($autoDir . '/cert.pem');
  }

  /**
   * A copied `.env` pointing at a checkout Homebrew never installed must not
   * block login: missing override paths fall through to generated certs.
   */
  public function testMissingExplicitPathsFallThroughToGeneratedCerts(): void {
    $directory   = $this->makeTemporaryDirectory('ca-cli-certs');
    $trustStore  = $this->recordingTrustStore();
    $provisioner = new CallbackCertificateProvisioner($directory, $trustStore);

    $material = $provisioner->ensure($this->config([
      'callbackCertFile' => $directory . '/missing-cert.pem',
      'callbackKeyFile'  => $directory . '/missing-key.pem',
    ]));

    self::assertSame($directory . '/cert.pem', $material->certFile);
    self::assertSame([$directory . '/ca.pem'], $trustStore->paths);
  }

  /**
   * @param array{
   *     callbackCertFile?: ?string,
   *     callbackKeyFile?: ?string,
   *     redirectUri?: string
   * } $overrides
   */
  private function config(array $overrides = []): Configuration {
    return Configuration::fromValues(
        [
          'apiBaseUrl'            => 'https://api.example.test',
          'authBaseUrl'           => 'https://auth.example.test',
          'authorizeUrl'          => 'https://auth.example.test/authorize',
          'bootstrapRefreshToken' => null,
          'callbackCertFile'      => $overrides['callbackCertFile'] ?? null,
          'callbackKeyFile'       => $overrides['callbackKeyFile'] ?? null,
          'callbackTimeout'       => 30,
          'clientId'              => 'client',
          'clientSecret'          => 'secret',
          'redirectUri'           => $overrides['redirectUri'] ?? 'https://conta-azul-cli.ddev.site:9876/callback',
          'scope'                 => null,
          'tokenPath'             => sys_get_temp_dir() . '/tokens.json',
          'tokenUrl'              => 'https://auth.example.test/token',
        ],
    );
  }

  /** @return TrustStoreInstaller&object{paths: list<string>} */
  private function recordingTrustStore(): TrustStoreInstaller {
    return new class implements TrustStoreInstaller {
      /** @var list<string> */
      public array $paths = [];

      public function ensureTrusted(string $caCertificatePath): void {
        $this->paths[] = $caCertificatePath;
      }
    };
  }
}
