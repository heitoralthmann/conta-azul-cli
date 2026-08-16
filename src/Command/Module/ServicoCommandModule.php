<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\ServicosClient;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers service commands, including their declarative resource operations. */
final class ServicoCommandModule implements CommandModuleInterface
{
  /** Connects the service API client and shared command collaborators. */
  public function __construct(
      private readonly ServicosClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array
  {
    $filters = [
      'busca' => 'busca',
      'codigo' => 'codigo',
      'ids' => 'ids',
      'status' => 'status',
    ];

    return [
      new ResourceListCommand(
          'servico list',
          'Lista serviços por filtros',
          $this->client->listServicos(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $filters,
      ),
      new ResourceJsonCommand(
          'servico create',
          'Cria um serviço',
          $this->client->createServico(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON do serviço',
      ),
      new ResourceIdCommand(
          'servico get',
          'Busca um serviço por ID',
          $this->client->getServico(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'ID do serviço',
      ),
      new ResourceIdJsonCommand(
          'servico update',
          'Atualiza parcialmente um serviço',
          $this->client->updateServico(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'ID do serviço',
          'Payload JSON do serviço',
      ),
      new ResourceJsonCommand(
          'servico delete',
          'Exclui serviços em lote',
          $this->client->deleteServicos(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON com os IDs dos serviços',
      ),
    ];
  }
}
