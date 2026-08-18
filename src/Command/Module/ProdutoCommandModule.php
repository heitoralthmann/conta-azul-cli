<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers product commands, including their declarative resource operations. */
final class ProdutoCommandModule implements CommandModuleInterface
{
  /** Connects the product API client and shared command collaborators. */
  public function __construct(
      private readonly ProdutosClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    $filters = [
      'busca'        => 'busca',
      'categoria-id' => 'categoria_id',
      'codigo'       => 'codigo',
      'ids'          => 'ids',
      'status'       => 'status',
    ];

    return [
      new ResourceListCommand(
          'produto list',
          'Lista produtos por filtros',
          $this->client->listProdutos(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $filters,
      ),
      new ResourceJsonCommand(
          'produto create',
          'Cria um produto',
          $this->client->createProduto(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON do produto',
      ),
      new ResourceIdCommand(
          'produto get',
          'Busca um produto por ID',
          $this->client->getProduto(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'ID do produto',
      ),
      new ResourceIdJsonCommand(
          'produto update',
          'Atualiza parcialmente um produto',
          $this->client->updateProduto(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'ID do produto',
          'Payload JSON do produto',
      ),
      new ResourceIdCommand(
          'produto delete',
          'Exclui um produto',
          $this->client->deleteProduto(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'ID do produto',
      ),
      new ResourceListCommand(
          'produto categorias',
          'Lista categorias de produtos',
          $this->client->listCategoriasProduto(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca'],
      ),
      new ResourceListCommand(
          'produto cest',
          'Lista códigos CEST',
          $this->client->listCest(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca', 'codigo' => 'codigo'],
      ),
      new ResourceListCommand(
          'produto ncm',
          'Lista códigos NCM',
          $this->client->listNcm(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca', 'codigo' => 'codigo'],
      ),
      new ResourceListCommand(
          'produto unidades-medida',
          'Lista unidades de medida',
          $this->client->listUnidadesMedida(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca', 'codigo' => 'codigo'],
      ),
      new ResourceListCommand(
          'produto ecommerce-categorias',
          'Lista categorias de ecommerce',
          $this->client->listCategoriasEcommerce(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca'],
      ),
      new ResourceListCommand(
          'produto ecommerce-marcas',
          'Lista marcas de ecommerce',
          $this->client->listMarcasEcommerce(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          ['busca' => 'busca'],
      ),
    ];
  }
}
