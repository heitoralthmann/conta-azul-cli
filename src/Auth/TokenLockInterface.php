<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Runs a callback while holding the process-wide token refresh lock. */
interface TokenLockInterface
{


  /**
   * Executes the callback under an exclusive lock.
   *
   * @template T
   * @param callable(): T $operation
   * @return T
   */
  public function synchronized(callable $operation): mixed;


}
