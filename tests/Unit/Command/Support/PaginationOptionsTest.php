<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Command\Support;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Error\CliException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/** @covers \ContaAzulCli\Command\Support\PaginationOptions */
final class PaginationOptionsTest extends TestCase
{
  public function testReadsConfiguredValues(): void
  {
    $options = PaginationOptions::fromInput(
        new ArrayInput(
            ['--pagina' => '3', '--tamanho-pagina' => '100'],
            new InputDefinition(
                [
                  new InputOption('pagina', null, InputOption::VALUE_REQUIRED),
                  new InputOption('tamanho-pagina', null, InputOption::VALUE_REQUIRED),
                ],
            ),
        ),
        new PaginationValidator(),
    );

    self::assertSame(3, $options->page());
    self::assertSame(100, $options->pageSize());
  }

  public function testUsesDefaultsWhenValuesAreMissing(): void
  {
    $options = PaginationOptions::fromInput(new ArrayInput([]), new PaginationValidator());

    self::assertSame(1, $options->page());
    self::assertSame(50, $options->pageSize());
  }

  public function testValidatesPageSize(): void
  {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('Tamanho de página inválido: 25.');

    PaginationOptions::fromInput(
        new ArrayInput(
            ['--tamanho-pagina' => '25'],
            new InputDefinition([new InputOption('tamanho-pagina', null, InputOption::VALUE_REQUIRED)]),
        ),
        new PaginationValidator(),
    );
  }
}
