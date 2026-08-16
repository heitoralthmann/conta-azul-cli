<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

use function dirname;
use function fclose;
use function flock;
use function fopen;
use function is_dir;
use function mkdir;

use const LOCK_EX;
use const LOCK_UN;

/** Coordinates token refreshes between concurrent CLI processes using a file. */
final class FileTokenLock implements TokenLockInterface
{
  /** @param string $tokenPath Path to the token file being protected. */
  public function __construct(private readonly string $tokenPath) {
  }

  /**
   * Executes an operation while holding an exclusive lock.
   *
   * @param callable(): T $operation
   *
   * @return T
   *
   * @template T
   */
  public function synchronized(callable $operation): mixed {
    $lockFile = $this->tokenPath . '.lock';
    $lockDir  = dirname($lockFile);
    if (! is_dir($lockDir)) {
      mkdir($lockDir, 0700, true);
    }

    // Silenced: failure is mapped to a stable CLI error below.
      // phpcs:ignore Generic.PHP.NoSilencedErrors
    $lock = @fopen($lockFile, 'c');
    if ($lock === false) {
      throw new CliException(
          ErrorKind::AuthFailed,
          false,
          'Não foi possível adquirir lock do arquivo de tokens.',
      );
    }

    flock($lock, LOCK_EX);
    try {
      return $operation();
    } finally {
      flock($lock, LOCK_UN);
      fclose($lock);
    }
  }
}
