<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;

use function chmod;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_readable;
use function is_string;
use function mkdir;
use function openssl_csr_new;
use function openssl_csr_sign;
use function openssl_error_string;
use function openssl_pkey_export;
use function openssl_pkey_get_private;
use function openssl_pkey_new;
use function openssl_x509_export;
use function openssl_x509_parse;
use function openssl_x509_read;
use function preg_match;
use function random_int;
use function strcasecmp;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function trim;
use function unlink;

use const LOCK_EX;
use const OPENSSL_KEYTYPE_RSA;

/**
 * Issues a private CA and a hostname leaf into a directory this CLI owns.
 *
 * The files live under `~/.config/conta-azul-cli/certs/`, next to tokens and
 * the user `.env` — never under a git checkout. Homebrew installs a PHAR,
 * not the repository, so a checkout-relative `.certs/` is unreachable on a
 * machine that only ran `brew install`.
 */
final class LocalCertificateAuthority
{
  private const int CA_DAYS   = 3650;
  private const int LEAF_DAYS = 825;
  private const string CA_CN  = 'conta-azul-cli local CA';

  /** Binds the authority to the directory that will hold its PEM files. */
  public function __construct(private readonly string $directory) {
  }

  /**
   * Returns a leaf covering `$host`, creating or renewing files as needed.
   *
   * @throws CliException When OpenSSL cannot issue the material or the
   *                      directory cannot be written.
   */
  public function issue(string $host): CallbackTlsMaterial {
    $this->assertHost($host);
    $this->ensureDirectory();
    $this->ensureCa();
    $this->ensureLeaf($host);

    return new CallbackTlsMaterial(
        $this->leafCertificatePath(),
        $this->leafKeyPath(),
        $this->caCertificatePath(),
    );
  }

  /** Rejects a host that cannot be interpolated into an OpenSSL config. */
  private function assertHost(string $host): void {
    if (preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'CA_REDIRECT_URI contém um host que não pode ser usado no certificado TLS: ' . $host,
      );
    }
  }

  /** Creates the certs directory with the same 0700 policy as tokens. */
  private function ensureDirectory(): void {
    if (is_dir($this->directory)) {
      return;
    }

    if (! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível criar o diretório ' . $this->directory . '.',
      );
    }

    chmod($this->directory, 0700);
  }

  /** Issues the CA once; later logins reuse it so the trust prompt happens once. */
  private function ensureCa(): void {
    if (is_readable($this->caCertificatePath()) && is_readable($this->caKeyPath())) {
      return;
    }

    $config = $this->writeConfig(self::CA_CN, null);
    try {
      $key  = $this->newKey($config);
      $csr  = $this->newCsr(self::CA_CN, $key, $config);
      $cert = openssl_csr_sign(
          $csr,
          null,
          $key,
          self::CA_DAYS,
          [
            'config'          => $config,
            'digest_alg'      => 'sha256',
            'x509_extensions' => 'v3_ca',
          ],
          random_int(1, 2_147_483_647),
      );
      if ($cert === false) {
        throw $this->opensslFailure('emitir a autoridade certificadora local');
      }

      $this->exportCertificate($cert, $this->caCertificatePath());
      $this->exportKey($key, $this->caKeyPath(), $config);
    } finally {
      unlink($config);
    }
  }

  /** Issues or replaces the leaf when it is missing, expired, or for another host. */
  private function ensureLeaf(string $host): void {
    if ($this->leafCovers($host)) {
      return;
    }

    $caPem    = file_get_contents($this->caCertificatePath());
    $caKeyPem = file_get_contents($this->caKeyPath());
    if ($caPem === false || $caKeyPem === false) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível ler a autoridade certificadora local em ' . $this->directory . '.',
      );
    }

    $caCert = openssl_x509_read($caPem);
    $caKey  = openssl_pkey_get_private($caKeyPem);
    if ($caCert === false || $caKey === false) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível carregar a autoridade certificadora local em ' . $this->directory . '.',
      );
    }

    $config = $this->writeConfig($host, $host);
    try {
      $key  = $this->newKey($config);
      $csr  = $this->newCsr($host, $key, $config);
      $cert = openssl_csr_sign(
          $csr,
          $caCert,
          $caKey,
          self::LEAF_DAYS,
          [
            'config'          => $config,
            'digest_alg'      => 'sha256',
            'x509_extensions' => 'v3_req',
          ],
          random_int(1, 2_147_483_647),
      );
      if ($cert === false) {
        throw $this->opensslFailure('emitir o certificado TLS do callback');
      }

      $this->exportCertificate($cert, $this->leafCertificatePath());
      $this->exportKey($key, $this->leafKeyPath(), $config);
    } finally {
      unlink($config);
    }
  }

  /** Whether the on-disk leaf is still valid and names this host in its SAN. */
  private function leafCovers(string $host): bool {
    if (! is_readable($this->leafCertificatePath()) || ! is_readable($this->leafKeyPath())) {
      return false;
    }

    $pem = file_get_contents($this->leafCertificatePath());
    if ($pem === false) {
      return false;
    }

    $parsed = openssl_x509_parse($pem);
    if ($parsed === false || ($parsed['validTo_time_t'] ?? 0) < time() + 86_400) {
      return false;
    }

    $extensions = $parsed['extensions'] ?? null;
    if (! is_array($extensions)) {
      return false;
    }

    $san = $extensions['subjectAltName'] ?? '';
    if (! is_string($san)) {
      return false;
    }

    foreach (explode(',', $san) as $entry) {
      if (strcasecmp(trim($entry), 'DNS:' . $host) === 0) {
        return true;
      }
    }

    return false;
  }

  /** @return non-empty-string */
  private function writeConfig(string $commonName, string|null $sanHost): string {
    $altNames = 'DNS.1 = localhost';
    if ($sanHost !== null) {
      $altNames = 'DNS.1 = ' . $sanHost;
    }

    $body = <<<CNF
[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
prompt = no
x509_extensions = v3_ca
req_extensions = v3_req

[ req_distinguished_name ]
CN = {$commonName}

[ v3_ca ]
basicConstraints = critical,CA:TRUE
keyUsage = critical,keyCertSign,cRLSign,digitalSignature
subjectKeyIdentifier = hash

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature,keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names
subjectKeyIdentifier = hash

[ alt_names ]
{$altNames}

CNF;

    $path = tempnam(sys_get_temp_dir(), 'ca-cli-openssl-');
    if ($path === false || file_put_contents($path, $body, LOCK_EX) === false) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível escrever a configuração temporária do OpenSSL.',
      );
    }

    return $path;
  }

  private function newKey(string $config): OpenSSLAsymmetricKey {
    $key = openssl_pkey_new(
        [
          'config'           => $config,
          'private_key_bits' => 2048,
          'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ],
    );
    if ($key === false) {
      throw $this->opensslFailure('gerar uma chave privada');
    }

    return $key;
  }

  private function newCsr(
      string $commonName,
      OpenSSLAsymmetricKey $key,
      string $config,
  ): OpenSSLCertificateSigningRequest {
    $csr = openssl_csr_new(
        ['commonName' => $commonName],
        $key,
        [
          'config'         => $config,
          'digest_alg'     => 'sha256',
          'req_extensions' => 'v3_req',
        ],
    );
    if (! $csr instanceof OpenSSLCertificateSigningRequest) {
      throw $this->opensslFailure('gerar o pedido de certificado');
    }

    return $csr;
  }

  private function exportCertificate(OpenSSLCertificate $certificate, string $path): void {
    $pem = '';
    if (! openssl_x509_export($certificate, $pem) || ! is_string($pem) || $pem === '') {
      throw $this->opensslFailure('exportar o certificado');
    }

    $this->writePrivateFile($path, $pem);
  }

  private function exportKey(OpenSSLAsymmetricKey $key, string $path, string $config): void {
    $pem      = '';
    $exported = openssl_pkey_export(
        $key,
        $pem,
        null,
        ['config' => $config, 'encrypt_key' => false],
    );
    if (! $exported || ! is_string($pem) || $pem === '') {
      throw $this->opensslFailure('exportar a chave privada');
    }

    $this->writePrivateFile($path, $pem);
  }

  private function writePrivateFile(string $path, string $contents): void {
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível escrever ' . $path . '.',
      );
    }

    chmod($path, 0600);
  }

  private function opensslFailure(string $action): CliException {
    $parts = [];
    while (true) {
      $error = openssl_error_string();
      if ($error === false) {
        break;
      }

      $parts[] = $error;
    }

    $detail = $parts === [] ? 'erro OpenSSL desconhecido' : implode('; ', $parts);

    return new CliException(
        ErrorKind::ClientError,
        false,
        'Não foi possível ' . $action . ': ' . $detail,
    );
  }

  private function caCertificatePath(): string {
    return $this->directory . '/ca.pem';
  }

  private function caKeyPath(): string {
    return $this->directory . '/ca-key.pem';
  }

  private function leafCertificatePath(): string {
    return $this->directory . '/cert.pem';
  }

  private function leafKeyPath(): string {
    return $this->directory . '/key.pem';
  }
}
