<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\FormatterRegistry;
use ContaAzulCli\Output\OutputFormatResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Verifies `--raw` / `--format` resolution before the command is bound.
 */
final class OutputFormatResolverTest extends TestCase
{
  private OutputFormatResolver $resolver;

  protected function setUp(): void {
    $this->resolver = new OutputFormatResolver(FormatterRegistry::withDefaults());
  }

  /** Omitting both flags keeps the registry default (TOON). */
  public function testDefaultIsToon(): void {
    self::assertSame('toon', $this->resolver->resolve($this->input('pessoa', 'list')));
  }

  /** `--raw` is the JSON alias. */
  public function testRawSelectsJson(): void {
    self::assertSame('json', $this->resolver->resolve($this->input('pessoa', 'list', '--raw')));
  }

  /** `--format=json` selects JSON without `--raw`. */
  public function testFormatEqualsJson(): void {
    self::assertSame('json', $this->resolver->resolve($this->input('pessoa', 'list', '--format=json')));
  }

  /** `--format json` as two argv tokens is accepted. */
  public function testFormatSeparateValue(): void {
    self::assertSame('json', $this->resolver->resolve($this->input('pessoa', 'list', '--format', 'json')));
  }

  /** `--format=toon` is explicit but still the default format. */
  public function testFormatEqualsToon(): void {
    self::assertSame('toon', $this->resolver->resolve($this->input('pessoa', 'list', '--format=toon')));
  }

  /** `--raw` and `--format=json` agree, so JSON wins without error. */
  public function testRawWithFormatJsonIsJson(): void {
    self::assertSame('json', $this->resolver->resolve($this->input('pessoa', 'list', '--raw', '--format=json')));
  }

  /** Global `--format=json` before the command name is still seen. */
  public function testFormatBeforeCommandName(): void {
    self::assertSame('json', $this->resolver->resolve($this->input('--format=json', 'pessoa', 'list')));
  }

  /** `--raw` contradicting `--format=toon` is a client error. */
  public function testRawContradictsToon(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('--raw seleciona JSON');

    $this->resolver->resolve($this->input('pessoa', 'list', '--raw', '--format=toon'));
  }

  /** Unknown `--format` values list the registered names. */
  public function testUnknownFormat(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('Formato de saída desconhecido: "yaml"');

    $this->resolver->resolve($this->input('pessoa', 'list', '--format=yaml'));
  }

  /** `--format` without a value is rejected before lookup. */
  public function testFormatWithoutValue(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('A opção --format exige um valor.');

    $this->resolver->resolve($this->input('pessoa', 'list', '--format'));
  }

  /** Builds unbound argv input as the real CLI process would see it. */
  private function input(string ...$argv): ArgvInput {
    return new ArgvInput(['ca', ...$argv]);
  }
}
