<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\OrcamentosClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers budget commands, including their declarative resource operations. */
final class OrcamentoCommandModule implements CommandModuleInterface
{
  /** Connects the budgets API client and shared command collaborators. */
  public function __construct(
      private readonly OrcamentosClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    $filters = [
      'campo-ordenado-ascendente'  => 'campo_ordenado_ascendente',
      'campo-ordenado-descendente' => 'campo_ordenado_descendente',
      'data-alteracao-ate'         => 'data_alteracao_ate',
      'data-alteracao-de'          => 'data_alteracao_de',
      'data-criacao-ate'           => 'data_criacao_ate',
      'data-criacao-de'            => 'data_criacao_de',
      'data-fim'                   => 'data_fim',
      'data-inicio'                => 'data_inicio',
      'termo-busca'                => 'termo_busca',
    ];

    return [
      new ResourceListCommand(
          'orcamento list',
          'Lista orçamentos por filtros',
          $this->client->listOrcamentos(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          $this->paginationValidator,
          $filters,
      ),
      new ResourceJsonCommand(
          'orcamento create',
          'Cria um orçamento',
          $this->client->createOrcamento(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Payload JSON do orçamento',
      ),
      new ResourceIdCommand(
          'orcamento get',
          'Busca um orçamento por ID',
          $this->client->getOrcamento(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Uuid do orçamento',
      ),
      new ResourceJsonCommand(
          'orcamento excluir-lote',
          'Exclui orçamentos em lote (até 10 uuids por chamada)',
          $this->client->excluirOrcamentosEmLote(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Payload JSON com os IDs dos orçamentos (campo "ids")',
      ),
    ];
  }
}
