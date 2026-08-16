<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Command\Support;

use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Error\CliException;
use PHPUnit\Framework\TestCase;

/** @covers \ContaAzulCli\Command\Support\JsonPayload */
final class JsonPayloadTest extends TestCase
{


  public function testDecodesAnObject(): void {
    self::assertSame(
      ['descricao' => 'Teste', 'valor' => 12.5],
      JsonPayload::object('{"descricao":"Teste","valor":12.5}'),
    );
  }


  public function testRejectsAJsonArray(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('JSON deve ser um objeto.');

    JsonPayload::object('[1,2,3]');
  }


  public function testRejectsInvalidJson(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('JSON inválido:');

    JsonPayload::object('{');
  }


  public function testRejectsAnEmptyOption(): void {
    $this->expectException(CliException::class);
    $this->expectExceptionMessage('A opção --json é obrigatória.');

    JsonPayload::object('');
  }


}
