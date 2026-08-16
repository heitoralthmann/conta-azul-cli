<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Command;

use ContaAzulCli\Command\Support\PeriodoPadrao;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PeriodoPadraoTest extends TestCase
{
  public function testCobreOMesCorrenteInteiro(): void
  {
    $periodo = new PeriodoPadrao(new DateTimeImmutable('2026-08-15 13:45:00'));

    self::assertSame('2026-08-01', $periodo->primeiroDia());
    self::assertSame('2026-08-31', $periodo->ultimoDia());
  }

  /** Fevereiro é onde um cálculo ingênuo de "último dia" quebra. */
  public function testRespeitaMesesCurtos(): void
  {
    $periodo = new PeriodoPadrao(new DateTimeImmutable('2026-02-10'));

    self::assertSame('2026-02-28', $periodo->ultimoDia());
    self::assertSame('2024-02-29', (new PeriodoPadrao(new DateTimeImmutable('2024-02-10')))->ultimoDia());
  }

  /** A API responde 400 se as datas vierem com timezone. */
  public function testInstantesNaoCarregamTimezone(): void
  {
    $periodo = new PeriodoPadrao(new DateTimeImmutable('2026-08-15 13:45:00'));

    self::assertSame('2026-08-01T00:00:00', $periodo->primeiroInstante());
    self::assertSame('2026-08-31T23:59:59', $periodo->ultimoInstante());
  }

  /** Um dia 31 não pode "vazar" para o mês seguinte ao normalizar o início. */
  public function testNaoTransbordaQuandoAReferenciaEhFimDeMes(): void
  {
    $periodo = new PeriodoPadrao(new DateTimeImmutable('2026-05-31 23:00:00'));

    self::assertSame('2026-05-01', $periodo->primeiroDia());
    self::assertSame('2026-05-31', $periodo->ultimoDia());
  }
}
