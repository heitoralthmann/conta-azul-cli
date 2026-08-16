<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\SleeperInterface;

/** Records delays without sleeping during protocol polling tests. */
final class RecordingSleeper implements SleeperInterface
{
  /** @var list<float> */
  public array $delays = [];

  /** Records a requested delay. */
  public function sleep(float $seconds): void {
    $this->delays[] = $seconds;
  }
}
