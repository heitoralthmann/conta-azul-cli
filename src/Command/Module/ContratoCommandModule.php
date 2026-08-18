<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\ContratosClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Contrato\ListCommand;
use ContaAzulCli\Command\Contrato\ProximoNumeroCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Command\Command;

/** Registers contract commands, including their declarative resource operations. */
final class ContratoCommandModule implements CommandModuleInterface
{
  /** Connects the contracts API client and shared command collaborators. */
  public function __construct(
      private readonly ContratosClient $client,
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
      new ListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new ResourceJsonCommand(
          'contrato create',
          'Cria um contrato',
          $this->client->createContrato(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON do contrato',
      ),
      new ProximoNumeroCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
    ];
  }
}
