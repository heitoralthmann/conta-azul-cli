<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Command\Support;

use ContaAzulCli\Command\Support\AsyncOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

#[CoversClass(AsyncOptions::class)]
final class AsyncOptionsTest extends TestCase
{
  public function testReadsConfiguredValues(): void {
    $options = AsyncOptions::fromInput(
        new ArrayInput(
            ['--poll-timeout' => '90', '--no-wait' => true],
            new InputDefinition(
                [
                  new InputOption('poll-timeout', null, InputOption::VALUE_REQUIRED),
                  new InputOption('no-wait', null, InputOption::VALUE_NONE),
                ],
            ),
        ),
    );

    self::assertSame(90, $options->pollTimeout());
    self::assertTrue($options->noWait());
  }

  public function testUsesDefaultsWhenValuesAreMissing(): void {
    $options = AsyncOptions::fromInput(new ArrayInput([]));

    self::assertSame(60, $options->pollTimeout());
    self::assertFalse($options->noWait());
  }

  public function testFallsBackToTheDefaultForANonNumericTimeout(): void {
    $options = AsyncOptions::fromInput(
        new ArrayInput(
            ['--poll-timeout' => 'invalid'],
            new InputDefinition([new InputOption('poll-timeout', null, InputOption::VALUE_REQUIRED)]),
        ),
    );

    self::assertSame(60, $options->pollTimeout());
  }
}
