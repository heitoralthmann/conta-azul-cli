<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Command\Venda\ImprimirCommand;
use ContaAzulCli\Command\Venda\ItensCommand;
use ContaAzulCli\Command\Venda\ProximoNumeroCommand;
use ContaAzulCli\Command\Venda\VendedoresCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers sale commands, including their declarative resource operations. */
final class VendaCommandModule implements CommandModuleInterface
{
  /** Connects the sales API client and shared command collaborators. */
  public function __construct(
      private readonly VendasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    $filters = [
      'campo-ordenado-ascendente'  => 'campo_ordenado_ascendente',
      'campo-ordenado-descendente' => 'campo_ordenado_descendente',
      'data-criacao-ate'           => 'data_criacao_ate',
      'data-criacao-de'            => 'data_criacao_de',
      'data-fim'                   => 'data_fim',
      'data-inicio'                => 'data_inicio',
      'termo-busca'                => 'termo_busca',
      'totais'                     => 'totais',
    ];

    return [
      new ResourceListCommand(
          'venda list',
          'Lista vendas por filtros',
          $this->client->listVendas(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $filters,
      ),
      new ResourceJsonCommand(
          'venda create',
          'Cria uma venda',
          $this->client->createVenda(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON da venda',
      ),
      new ResourceIdCommand(
          'venda get',
          'Busca uma venda por ID',
          $this->client->getVenda(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Uuid ou id legado da venda',
      ),
      new ResourceIdJsonCommand(
          'venda update',
          'Atualiza uma venda',
          $this->client->updateVenda(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Uuid da venda',
          'Payload JSON da venda',
      ),
      new ImprimirCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ItensCommand($this->client, $this->errorEnvelope, $this->jsonRenderer, $this->paginationValidator),
      new VendedoresCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ProximoNumeroCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ResourceJsonCommand(
          'venda excluir-lote',
          'Exclui vendas em lote (até 10 uuids por chamada)',
          $this->client->excluirVendasEmLote(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON com os IDs das vendas (campo "ids")',
      ),
    ];
  }
}
