<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

use function fclose;
use function getenv;
use function is_callable;
use function is_resource;
use function is_string;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_OS_FAMILY;

/**
 * Installs a local CA into the current user's OS trust store.
 *
 * Browsers will not follow an HTTPS redirect to `*.ddev.site` unless they
 * trust whoever signed the callback certificate. mkcert did this for a
 * checkout; a Homebrew install has no checkout and no mkcert, so the CLI
 * has to do it itself, once, during `ca auth login`.
 */
final class SystemTrustStoreInstaller implements TrustStoreInstaller
{
  /**
   * @param string                             $osFamily   PHP_OS_FAMILY, overridable in tests.
   * @param (callable(list<string>): int)|null $runCommand Optional command runner; defaults to proc_open.
   */
  public function __construct(
      private readonly string $osFamily = PHP_OS_FAMILY,
      private readonly mixed $runCommand = null,
  ) {
  }

  /** {@inheritDoc} */
  public function ensureTrusted(string $caCertificatePath): void {
    if ($this->isTrusted($caCertificatePath)) {
      return;
    }

    $this->install($caCertificatePath);

    if ($this->isTrusted($caCertificatePath)) {
      return;
    }

    throw new CliException(
        ErrorKind::ClientError,
        false,
        'Não foi possível instalar a autoridade certificadora local no trust store do sistema. '
            . 'O navegador recusará o callback HTTPS até você confiar neste arquivo e rodar '
            . '"ca auth login" de novo: ' . $caCertificatePath,
    );
  }

  /** Whether the CA is already accepted for TLS server authentication. */
  private function isTrusted(string $caCertificatePath): bool {
    $command = $this->verifyCommand($caCertificatePath);
    if ($command === null) {
      return false;
    }

    return $this->run($command) === 0;
  }

  /** Adds the CA to the user trust store, prompting the OS as needed. */
  private function install(string $caCertificatePath): void {
    $command = $this->installCommand($caCertificatePath);
    if ($command === null) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Não há um comando conhecido para instalar a CA local nesta plataforma. '
              . 'Confie manualmente neste arquivo e rode "ca auth login" de novo: '
              . $caCertificatePath,
      );
    }

    $this->run($command);
  }

  /** @return list<string>|null */
  private function verifyCommand(string $caCertificatePath): array|null {
    return match ($this->osFamily) {
      'Darwin' => ['/usr/bin/security', 'verify-cert', '-c', $caCertificatePath],
      'Windows' => ['certutil', '-user', '-verify', $caCertificatePath],
      'Linux' => $this->nssCommand(['-L', '-n', 'conta-azul-cli local CA']),
      default => null,
    };
  }

  /** @return list<string>|null */
  private function installCommand(string $caCertificatePath): array|null {
    return match ($this->osFamily) {
      'Darwin' => $this->darwinInstallCommand($caCertificatePath),
      'Windows' => ['certutil', '-user', '-addstore', 'Root', $caCertificatePath],
      'Linux' => $this->nssCommand(
          ['-A', '-t', 'C,,', '-n', 'conta-azul-cli local CA', '-i', $caCertificatePath],
      ),
      default => null,
    };
  }

  /** @return list<string>|null */
  private function darwinInstallCommand(string $caCertificatePath): array|null {
    $home = getenv('HOME');
    if (! is_string($home) || $home === '') {
      return null;
    }

    return [
      '/usr/bin/security',
      'add-trusted-cert',
      '-r',
      'trustRoot',
      '-k',
      $home . '/Library/Keychains/login.keychain-db',
      $caCertificatePath,
    ];
  }

  /**
   * @param list<string> $arguments
   *
   * @return list<string>|null
   */
  private function nssCommand(array $arguments): array|null {
    $home = getenv('HOME');
    if (! is_string($home) || $home === '') {
      return null;
    }

    return ['certutil', '-d', 'sql:' . $home . '/.pki/nssdb', ...$arguments];
  }

  /** @param list<string> $command */
  private function run(array $command): int {
    $runner = $this->runCommand;
    if (is_callable($runner)) {
      return (int) $runner($command);
    }

    return $this->procOpen($command);
  }

  /** @param list<string> $command */
  private function procOpen(array $command): int {
    $pipes   = [];
    $process = proc_open(
        $command,
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    if (! is_resource($process)) {
      return 1;
    }

    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);

    return $status === -1 ? 1 : $status;
  }
}
