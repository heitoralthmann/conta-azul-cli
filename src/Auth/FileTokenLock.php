<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

/** Coordinates token refreshes between concurrent CLI processes using a file. */
final class FileTokenLock implements TokenLockInterface
{


    /** @param string $tokenPath Path to the token file being protected. */
    public function __construct(private readonly string $tokenPath) {
    }


    /**
     * Executes an operation while holding an exclusive lock.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function synchronized(callable $operation): mixed {
        $lockFile = $this->tokenPath . '.lock';
        $lockDir  = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0700, TRUE);
        }

        // Silenced: failure is mapped to a stable CLI error below.
        // phpcs:ignore Generic.PHP.NoSilencedErrors
        $lock = @fopen($lockFile, 'c');
        if ($lock === FALSE) {
            throw new CliException(
              ErrorKind::AuthFailed,
              FALSE,
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
