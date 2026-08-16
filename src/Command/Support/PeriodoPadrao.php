<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use DateTimeImmutable;

/**
 * Vários endpoints exigem intervalo de datas e respondem 400 sem ele. Em vez de
 * obrigar o operador a digitar datas para a consulta mais banal, os comandos
 * caem no mês corrente — e avisam em stderr qual recorte aplicaram, para que o
 * default nunca passe despercebido.
 */
final class PeriodoPadrao
{
  private readonly DateTimeImmutable $referencia;


  /** Creates a month-range helper anchored to the supplied instant or now. */
  public function __construct(?DateTimeImmutable $referencia=NULL) {
    $this->referencia = $referencia ?? new DateTimeImmutable('now');
  }


  /** Returns the first calendar day of the reference month. */
  public function primeiroDia(): string {
    return $this->referencia->modify('first day of this month')->format('Y-m-d');
  }


  /** Returns the last calendar day of the reference month. */
  public function ultimoDia(): string {
    return $this->referencia->modify('last day of this month')->format('Y-m-d');
  }


  /** A API recusa timezone nestes campos; o formato é ISO 8601 puro. */
  public function primeiroInstante(): string {
    return $this->referencia->modify('first day of this month')->format('Y-m-d\T00:00:00');
  }


  /** Returns the final second of the reference month without timezone data. */
  public function ultimoInstante(): string {
    return $this->referencia->modify('last day of this month')->format('Y-m-d\T23:59:59');
  }


}
