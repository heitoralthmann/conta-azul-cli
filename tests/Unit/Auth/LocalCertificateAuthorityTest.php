<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Auth\LocalCertificateAuthority;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Tests\Support\PosixPermissions;
use ContaAzulCli\Tests\Support\TemporaryDirectories;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_get_contents;
use function openssl_x509_parse;
use function proc_close;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_socket_get_name;
use function stream_socket_server;
use function strrpos;
use function substr;
use function var_export;

use const PHP_BINARY;

final class LocalCertificateAuthorityTest extends TestCase
{
  use PosixPermissions;
  use TemporaryDirectories;

  /** @var list<resource> */
  private array $processes = [];

  protected function tearDown(): void {
    foreach ($this->processes as $process) {
        // phpcs:ignore Generic.PHP.NoSilencedErrors -- process may already have exited.
      @proc_terminate($process);
        // phpcs:ignore Generic.PHP.NoSilencedErrors
      @proc_close($process);
    }

    $this->processes = [];
    $this->removeTemporaryDirectories();
  }

  public function testIssuesALeafWhoseSanMatchesTheHost(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $material  = (new LocalCertificateAuthority($directory))->issue('conta-azul-cli.ddev.site');

    self::assertNotNull($material->certFile);
    self::assertNotNull($material->keyFile);
    self::assertNotNull($material->caFile);
    self::assertFileExists($material->certFile);
    self::assertFileExists($material->keyFile);
    self::assertFileExists($material->caFile);

    $parsed = openssl_x509_parse((string) file_get_contents($material->certFile));
    self::assertIsArray($parsed);
    self::assertStringContainsString(
        'DNS:conta-azul-cli.ddev.site',
        (string) ($parsed['extensions']['subjectAltName'] ?? ''),
    );
  }

  public function testReusesAnExistingLeafForTheSameHost(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $authority = new LocalCertificateAuthority($directory);
    $first     = $authority->issue('conta-azul-cli.ddev.site');
    $firstLeaf = (string) file_get_contents((string) $first->certFile);
    $firstCa   = (string) file_get_contents((string) $first->caFile);

    $second = $authority->issue('conta-azul-cli.ddev.site');

    self::assertSame($firstLeaf, file_get_contents((string) $second->certFile));
    self::assertSame($firstCa, file_get_contents((string) $second->caFile));
  }

  public function testReissuesTheLeafWhenTheHostChangesButKeepsTheCa(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $authority = new LocalCertificateAuthority($directory);
    $first     = $authority->issue('conta-azul-cli.ddev.site');
    $firstCa   = (string) file_get_contents((string) $first->caFile);
    $firstLeaf = (string) file_get_contents((string) $first->certFile);

    $second     = $authority->issue('other.ddev.site');
    $secondLeaf = (string) file_get_contents((string) $second->certFile);
    $parsed     = openssl_x509_parse($secondLeaf);

    self::assertNotSame($firstLeaf, $secondLeaf);
    self::assertSame($firstCa, file_get_contents((string) $second->caFile));
    self::assertIsArray($parsed);
    self::assertStringContainsString('DNS:other.ddev.site', (string) ($parsed['extensions']['subjectAltName'] ?? ''));
  }

  public function testRejectsAHostThatCannotGoIntoAnOpensslConfig(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');

    $this->expectException(CliException::class);
    $this->expectExceptionMessageMatches('/host que não pode ser usado/');

    (new LocalCertificateAuthority($directory))->issue("bad\nhost");
  }

  public function testPrivateKeysAreNotReadableByOtherUsers(): void {
    self::requirePosixPermissions();

    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $material  = (new LocalCertificateAuthority($directory))->issue('conta-azul-cli.ddev.site');

    self::assertPermissions('700', $directory);
    self::assertPermissions('600', (string) $material->keyFile);
    self::assertPermissions('600', (string) $material->caFile);
  }

  /**
   * The generated pair has to be something PHP's TLS listener will actually
   * present: a SAN mismatch or a CA:TRUE leaf would pass openssl_x509_parse
   * and still fail the browser redirect.
   */
  public function testGeneratedPairServesATlsCallback(): void {
    $directory = $this->makeTemporaryDirectory('ca-cli-certs');
    $material  = (new LocalCertificateAuthority($directory))->issue('conta-azul-cli.ddev.site');
    $port      = $this->freePort();
    $this->spawnTlsClient(
        $port,
        (string) $material->caFile,
        "GET /callback?code=from-tls&state=st-tls HTTP/1.1\r\nHost: conta-azul-cli.ddev.site\r\n\r\n",
    );

    $server = new CallbackServer(
        port: $port,
        timeoutSeconds: 15,
        certFile: $material->certFile,
        keyFile: $material->keyFile,
    );

    self::assertSame('from-tls', $server->waitForCallback('st-tls'));
  }

  private function freePort(): int {
    $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    self::assertIsResource($probe, 'não foi possível reservar uma porta livre: ' . $errstr);
    $name = stream_socket_get_name($probe, false);
    fclose($probe);
    self::assertIsString($name);

    return (int) substr($name, (int) strrpos($name, ':') + 1);
  }

  private function spawnTlsClient(int $port, string $caFile, string $payload): void {
    $script = sprintf(
        '$ctx = stream_context_create(["ssl" => ['
            . '"cafile" => %s, "verify_peer" => true, "verify_peer_name" => true,'
            . ' "peer_name" => "conta-azul-cli.ddev.site", "allow_self_signed" => false,'
            . ']]);'
            . '$sock = false; $deadline = microtime(true) + 10;'
            . 'while ($sock === false && microtime(true) < $deadline) {'
            . '  $sock = @stream_socket_client("ssl://127.0.0.1:%d", $e, $s, 1,'
            . '    STREAM_CLIENT_CONNECT, $ctx);'
            . '  if ($sock === false) { usleep(50000); }'
            . '}'
            . 'if ($sock === false) { fwrite(STDERR, $s ?? "connect failed"); exit(1); }'
            . 'fwrite($sock, %s); sleep(2);',
        // Not a debugging leftover: this generates the PHP literal embedded in
        // the subprocess script below, and var_export() is the standard tool
        // for that.
        // phpcs:ignore ContaAzulCli.Functions.DebuggingFunctions
        var_export($caFile, true),
        $port,
        // phpcs:ignore ContaAzulCli.Functions.DebuggingFunctions
        var_export($payload, true),
    );

    $process = proc_open(
        [PHP_BINARY, '-r', $script],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    self::assertIsResource($process);
    $this->processes[] = $process;
  }
}
