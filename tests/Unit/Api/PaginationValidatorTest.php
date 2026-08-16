<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use PHPUnit\Framework\TestCase;

final class PaginationValidatorTest extends TestCase
{
  private PaginationValidator $validator;


  protected function setUp(): void {
    $this->validator = new PaginationValidator();
  }


  #[\PHPUnit\Framework\Attributes\DataProvider('validPageSizes')]
  public function testValidPageSizesPass(int $size): void {
    $this->expectNotToPerformAssertions();
    $this->validator->validatePageSize($size);
  }


  public static function validPageSizes(): array {
    return [[10], [20], [50], [100], [200], [500], [1000]];
  }


  public function testInvalidSize25ThrowsCliException(): void {
    $this->expectException(CliException::class);
    $this->validator->validatePageSize(25);
  }


  public function testInvalidSize0ThrowsClientError(): void {
    try {
      $this->validator->validatePageSize(0);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
      self::assertFalse($e->retryable);
    }
  }


  public function testInvalidSize2000ThrowsClientError(): void {
    try {
      $this->validator->validatePageSize(2000);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
    }
  }


}
