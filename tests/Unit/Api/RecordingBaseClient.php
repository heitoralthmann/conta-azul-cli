<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\BaseClient;

/** Captures the backoff schedule instead of actually waiting. */
final class RecordingBaseClient extends BaseClient
{
  /** @var list<float> */
  public array $sleeps = [];

  protected function sleep(float $seconds): void
  {
    $this->sleeps[] = $seconds;
  }
}
