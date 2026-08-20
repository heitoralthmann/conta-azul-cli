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
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Transferencia\ListCommand as TransferenciaListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Command\Command;

/** Registers commands backed by the financial API client. */
final class FinanceiroCommandModule implements CommandModuleInterface
{
  /** Connects financial API services and shared command collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
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
          $this->responseRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ContaAReceberCreateCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new ResourceJsonCommand(
          'cobranca create',
          'Gera uma cobrança (boleto, PIX ou link de pagamento) para uma parcela',
          $this->client->gerarCobranca(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Payload JSON da cobrança (conta_bancaria, descricao_fatura, id_parcela, '
              . 'data_vencimento e tipo obrigatórios)',
      ),
      new ResourceIdCommand(
          'cobranca get',
          'Busca uma cobrança por ID',
          $this->client->getCobranca(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da cobrança',
      ),
      new ResourceIdCommand(
          'cobranca delete',
          'Cancela uma cobrança gerada incorretamente ou a invalidar antes do pagamento',
          $this->client->deleteCobranca(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da cobrança',
      ),
      new ContaAPagarListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ContaAPagarCreateCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new ParcelaGetCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new ResourceIdJsonCommand(
          'parcela update',
          'Atualiza parcialmente uma parcela (não dá baixa; para quitar use "parcela baixar")',
          $this->client->updateParcela(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da parcela',
          'Payload JSON da parcela (campo "versao" com a versão atual é obrigatório)',
      ),
      new BaixarCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new ParcelaListCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new ResourceIdJsonCommand(
          'baixa create',
          'Registra uma baixa (quitação) para uma parcela',
          $this->client->createBaixa(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da parcela',
          'Payload JSON da baixa (data_pagamento, conta_financeira e composicao_valor obrigatórios)',
      ),
      new ResourceIdCommand(
          'baixa list',
          'Lista as baixas de uma parcela',
          $this->client->listBaixasByParcela(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da parcela',
      ),
      new ResourceIdCommand(
          'baixa get',
          'Busca uma baixa por ID',
          $this->client->getBaixa(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da baixa',
      ),
      new ResourceIdJsonCommand(
          'baixa update',
          'Atualiza parcialmente uma baixa',
          $this->client->updateBaixa(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da baixa',
          'Payload JSON da baixa (campo "versao" com a versão atual é obrigatório)',
      ),
      new ResourceIdCommand(
          'baixa delete',
          'Exclui uma baixa, impactando o saldo e o histórico financeiro da parcela',
          $this->client->deleteBaixa(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid da baixa',
      ),
      new ContaFinanceiraListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
      ),
      new ContaFinanceiraSaldoCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new TransferenciaListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new CategoriaListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
      ),
      new CategoriaConfiguracaoPadraoCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new CategoriaDreCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new CentroDeCustoListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
      ),
      new ResourceJsonCommand(
          'centro-de-custo create',
          'Cria um centro de custo',
          $this->client->createCentroDeCusto(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Payload JSON do centro de custo (nome obrigatório; codigo opcional)',
      ),
      new AlteracoesCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new SaldoInicialCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ProtocoloGetCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
    ];
  }
}
