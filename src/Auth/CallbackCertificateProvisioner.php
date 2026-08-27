<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Config\HomeDirectory;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

use function is_readable;
use function is_string;
use function parse_url;

use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

/**
 * Resolves the TLS material `ca auth login` needs for the local callback.
 *
 * Explicit `CA_CALLBACK_CERT` / `CA_CALLBACK_KEY` still win when the files
 * exist — that is the checkout-plus-mkcert path. When they are absent, which
 * is the Homebrew-on-a-new-machine case, a CA and leaf are issued into
 * `~/.config/conta-azul-cli/certs/` and the CA is installed in the user
 * trust store. Plain `http://` redirect URIs skip TLS entirely.
 */
final class CallbackCertificateProvisioner
{
  /**
   * @param string|null         $directory  Override for tests; default is the user config certs dir.
   * @param TrustStoreInstaller $trustStore Where to install a CA this CLI issued.
   */
  public function __construct(
      private readonly string|null $directory = null,
      private readonly TrustStoreInstaller $trustStore = new SystemTrustStoreInstaller(),
  ) {
  }

  /**
   * Returns the cert/key pair the callback listener should use, generating
   * them when the redirect URI is HTTPS and nothing usable is on disk.
   *
   * @throws CliException When HTTPS is required and material cannot be produced.
   */
  public function ensure(Configuration $config): CallbackTlsMaterial {
    if (! $this->usesTls($config)) {
      return new CallbackTlsMaterial(null, null);
    }

    $explicit = $this->explicitMaterial($config);
    if ($explicit !== null) {
      return $explicit;
    }

    $material = (new LocalCertificateAuthority($this->directory()))->issue($this->redirectHost($config));
    if ($material->caFile !== null) {
      $this->trustStore->ensureTrusted($material->caFile);
    }

    return $material;
  }

  /** Operator-supplied paths that actually exist; missing checkout files are ignored. */
  private function explicitMaterial(Configuration $config): CallbackTlsMaterial|null {
    $cert = $config->callbackCertFile;
    $key  = $config->callbackKeyFile;
    if ($cert === null || $key === null) {
      return null;
    }

    if (! is_readable($cert) || ! is_readable($key)) {
      return null;
    }

    return new CallbackTlsMaterial($cert, $key);
  }

  private function usesTls(Configuration $config): bool {
    return parse_url($config->redirectUri, PHP_URL_SCHEME) === 'https';
  }

  private function redirectHost(Configuration $config): string {
    $host = parse_url($config->redirectUri, PHP_URL_HOST);
    if (! is_string($host) || $host === '') {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'CA_REDIRECT_URI não contém um host válido: ' . $config->redirectUri,
      );
    }

    return $host;
  }

  private function directory(): string {
    if ($this->directory !== null) {
      return $this->directory;
    }

    $home = HomeDirectory::resolve();
    if ($home === null) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não foi possível determinar o diretório home do usuário. '
              . 'Defina CA_CALLBACK_CERT e CA_CALLBACK_KEY com caminhos absolutos.',
      );
    }

    return $home . ConfigFileLocator::USER_DIRECTORY . '/certs';
  }
}
