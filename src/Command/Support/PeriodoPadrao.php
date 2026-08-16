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


    public function __construct(?DateTimeImmutable $referencia=NULL) {
        $this->referencia = $referencia ?? new DateTimeImmutable('now');
    }


    public function primeiroDia(): string {
        return $this->referencia->modify('first day of this month')->format('Y-m-d');
    }


    public function ultimoDia(): string {
        return $this->referencia->modify('last day of this month')->format('Y-m-d');
    }


    /** A API recusa timezone nestes campos; o formato é ISO 8601 puro. */
    public function primeiroInstante(): string {
        return $this->referencia->modify('first day of this month')->format('Y-m-d\T00:00:00');
    }


    public function ultimoInstante(): string {
        return $this->referencia->modify('last day of this month')->format('Y-m-d\T23:59:59');
    }


}
