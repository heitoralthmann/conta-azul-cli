<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\PageSizeRule;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationValidatorTest extends TestCase
{
  private PaginationValidator $validator;

  protected function setUp(): void {
    $this->validator = new PaginationValidator();
  }

  #[DataProvider('validPageSizes')]
  public function testValidPageSizesPass(int $size): void {
    $this->expectNotToPerformAssertions();
    $this->validator->validatePageSize($size);
  }

  /** @return list<array{int}> */
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

  #[DataProvider('sizesTheCappedEndpointsAccept')]
  public function testCappedEndpointsStillAcceptSizesUpToOneHundred(int $size): void {
    $this->expectNotToPerformAssertions();
    $this->validator->validatePageSize($size, PaginationValidator::CAPPED_MAX_SIZE);
  }

  /** @return list<array{int}> */
  public static function sizesTheCappedEndpointsAccept(): array {
    return [[10], [20], [50], [100]];
  }

  /**
   * `GET /v1/servicos`, `/v1/notas-fiscais` and `/v1/notas-fiscais-servico`
   * answer 400 above 100. Validating them against the widest list let those
   * sizes reach the API, which is the one thing local validation exists to
   * prevent — measured against production on 2026-08-19.
   */
  #[DataProvider('sizesTheCappedEndpointsReject')]
  public function testCappedEndpointsRejectSizesTheApiWouldRefuse(int $size): void {
    try {
      $this->validator->validatePageSize($size, PaginationValidator::CAPPED_MAX_SIZE);
      self::fail('Expected CliException for size ' . $size);
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
      self::assertFalse($e->retryable);
    }
  }

  /** @return list<array{int}> */
  public static function sizesTheCappedEndpointsReject(): array {
    return [[200], [500], [1000]];
  }

  /** The message must offer only what this endpoint takes, not the full list. */
  public function testCappedMessageListsOnlyTheAcceptedSizes(): void {
    try {
      $this->validator->validatePageSize(200, PaginationValidator::CAPPED_MAX_SIZE);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(
          'Tamanho de página inválido: 200. Valores aceitos: 10, 20, 50, 100',
          $e->getMessage(),
      );
    }
  }

  /** Omitting the limit keeps the historical behavior for every other endpoint. */
  public function testDefaultLimitStillAcceptsTheWidestSize(): void {
    $this->expectNotToPerformAssertions();
    $this->validator->validatePageSize(1000);
    $this->validator->validatePageSize(1000, PaginationValidator::DEFAULT_MAX_SIZE);
  }

  /**
   * `GET /v1/captura/documentos/status` takes any integer in 1..20 — it is
   * the one listing whose rule is a range rather than the discrete steps.
   * Measured against production on 2026-08-19: 1, 5 and 15 all answer 200.
   */
  #[DataProvider('sizesCapturaStatusAccepts')]
  public function testCapturaStatusAcceptsEverySizeUpToItsMaximum(int $size): void {
    $this->expectNotToPerformAssertions();
    $this->validator->validatePageSize(
        $size,
        PaginationValidator::CAPTURA_MAX_SIZE,
        PageSizeRule::AnySizeUpToMax,
    );
  }

  /** @return list<array{int}> */
  public static function sizesCapturaStatusAccepts(): array {
    return [[1], [5], [10], [15], [20]];
  }

  /**
   * The discrete rule was wrong in both directions here: it let 1000 reach
   * the API (which answers 400) and rejected 15 (which the API accepts).
   */
  #[DataProvider('sizesCapturaStatusRejects')]
  public function testCapturaStatusRejectsSizesOutsideItsRange(int $size): void {
    try {
      $this->validator->validatePageSize(
          $size,
          PaginationValidator::CAPTURA_MAX_SIZE,
          PageSizeRule::AnySizeUpToMax,
      );
      self::fail('Expected CliException for size ' . $size);
    } catch (CliException $e) {
      self::assertSame(ErrorKind::ClientError, $e->kind);
      self::assertFalse($e->retryable);
    }
  }

  /** @return list<array{int}> */
  public static function sizesCapturaStatusRejects(): array {
    return [[0], [-1], [21], [50], [100], [1000]];
  }

  /** A range endpoint must not advertise the discrete steps it does not use. */
  public function testCapturaStatusMessageDescribesTheRange(): void {
    try {
      $this->validator->validatePageSize(
          21,
          PaginationValidator::CAPTURA_MAX_SIZE,
          PageSizeRule::AnySizeUpToMax,
      );
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame('Tamanho de página inválido: 21. Valores aceitos: 1 a 20', $e->getMessage());
    }
  }

  /** The discrete rule stays the default, so no other endpoint changes behavior. */
  public function testDiscreteRuleRemainsTheDefaultAndStillRejectsInBetweenSizes(): void {
    try {
      $this->validator->validatePageSize(15);
      self::fail('Expected CliException');
    } catch (CliException $e) {
      self::assertSame(
          'Tamanho de página inválido: 15. Valores aceitos: 10, 20, 50, 100, 200, 500, 1000',
          $e->getMessage(),
      );
    }
  }
}
