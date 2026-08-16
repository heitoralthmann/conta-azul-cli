<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Runs a callback while holding the process-wide token refresh lock. */
interface TokenLockInterface
{
  /**
   * Executes the callback under an exclusive lock.
   *
   * @param callable(): T $operation
   *
   * @return T
   *
   * @template T
   */
  public function synchronized(callable $operation): mixed;
}
