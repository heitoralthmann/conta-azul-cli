<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Config;

use ContaAzulCli\Config\EnvironmentSnapshot;
use PHPUnit\Framework\TestCase;

final class EnvironmentSnapshotTest extends TestCase
{
  public function testReportsCapturedVariables(): void {
    $snapshot = EnvironmentSnapshot::fromArray(['CA_CLIENT_ID' => 'abc']);

    self::assertTrue($snapshot->has('CA_CLIENT_ID'));
    self::assertSame('abc', $snapshot->get('CA_CLIENT_ID'));
  }

  public function testTreatsAMissingVariableAsAbsent(): void {
    $snapshot = EnvironmentSnapshot::fromArray([]);

    self::assertFalse($snapshot->has('CA_CLIENT_ID'));
    self::assertNull($snapshot->get('CA_CLIENT_ID'));
  }

  /** Empty counts as absent, matching the loader that falls back to the default. */
  public function testTreatsAnEmptyVariableAsAbsent(): void {
    $snapshot = EnvironmentSnapshot::fromArray(['CA_SCOPE' => '']);

    self::assertFalse($snapshot->has('CA_SCOPE'));
    self::assertNull($snapshot->get('CA_SCOPE'));
  }

  /** The CLI SAPI fills $_SERVER with the real environment, whatever variables_order says. */
  public function testCapturesTheProcessEnvironment(): void {
    $_SERVER['CA_SNAPSHOT_PROBE'] = 'presente';

    try {
      self::assertSame('presente', EnvironmentSnapshot::capture()->get('CA_SNAPSHOT_PROBE'));
    } finally {
      unset($_SERVER['CA_SNAPSHOT_PROBE']);
    }
  }
}
