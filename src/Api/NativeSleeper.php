<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use function mt_getrandmax;
use function mt_rand;
use function usleep;

/** Sleeps using the process clock and adds the transport's retry jitter. */
final class NativeSleeper implements SleeperInterface
{
  /**
   * Waits for a duration with up to twenty percent positive or negative jitter.
   */
  public function sleep(float $seconds): void {
    $jitter = $seconds * 0.2;
    $actual = $seconds + (mt_rand() / mt_getrandmax() * 2.0 - 1.0) * $jitter;
    usleep((int) ($actual * 1_000_000));
  }
}
