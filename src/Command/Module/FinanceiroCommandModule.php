<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Categoria\ConfiguracaoPadraoCommand as CategoriaConfiguracaoPadraoCommand;
use ContaAzulCli\Command\Categoria\DreCommand as CategoriaDreCommand;
use ContaAzulCli\Command\Categoria\ListCommand as CategoriaListCommand;
use ContaAzulCli\Command\CentroDeCusto\ListCommand as CentroDeCustoListCommand;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\ContaAPagar\CreateCommand as ContaAPagarCreateCommand;
use ContaAzulCli\Command\ContaAPagar\ListCommand as ContaAPagarListCommand;
use ContaAzulCli\Command\ContaAReceber\CreateCommand as ContaAReceberCreateCommand;
use ContaAzulCli\Command\ContaAReceber\ListCommand as ContaAReceberListCommand;
use ContaAzulCli\Command\ContaFinanceira\ListCommand as ContaFinanceiraListCommand;
use ContaAzulCli\Command\ContaFinanceira\SaldoCommand as ContaFinanceiraSaldoCommand;
use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Financeiro\SaldoInicialCommand;
use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Command\Parcela\GetCommand as ParcelaGetCommand;
use ContaAzulCli\Command\Parcela\ListCommand as ParcelaListCommand;
use ContaAzulCli\Command\Protocolo\GetCommand as ProtocoloGetCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Transferencia\ListCommand as TransferenciaListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Command\Command;

/** Registers commands backed by the financial API client. */
final class FinanceiroCommandModule implements CommandModuleInterface
{
  /** Connects financial API services and shared command collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
      private readonly WarningEnvelope $warningEnvelope,
      private readonly PeriodoPadrao $periodoPadrao,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new ContaAReceberListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ContaAReceberCreateCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ContaAPagarListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ContaAPagarCreateCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ParcelaGetCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new BaixarCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ParcelaListCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ContaFinanceiraListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
      ),
      new ContaFinanceiraSaldoCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new TransferenciaListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new CategoriaListCommand($this->client, $this->errorEnvelope, $this->jsonRenderer, $this->paginationValidator),
      new CategoriaConfiguracaoPadraoCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new CategoriaDreCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new CentroDeCustoListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
      ),
      new AlteracoesCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new SaldoInicialCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ProtocoloGetCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
    ];
  }
}
