<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\CapturaClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Captura\EnviarCommand;
use ContaAzulCli\Command\Captura\StatusCommand;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers document capture (Captura) commands, including their declarative resource operations. */
final class CapturaCommandModule implements CommandModuleInterface
{
  /** Connects the capture API client and shared command collaborators. */
  public function __construct(
      private readonly CapturaClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new EnviarCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new StatusCommand($this->client, $this->errorEnvelope, $this->responseRenderer, $this->paginationValidator),
      new ResourceIdCommand(
          'captura get',
          'Consulta os dados extraídos da captura de um documento enviado',
          $this->client->getCaptura(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Id da captura (id_captura, obtido em "captura status")',
      ),
      new ResourceIdCommand(
          'captura aceitar',
          'Aceita a prévia do evento financeiro sugerida pela captura',
          $this->client->aceitarCaptura(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Id da captura a ser aceita',
      ),
      new ResourceIdCommand(
          'captura recusar',
          'Recusa a prévia do evento financeiro sugerida pela captura',
          $this->client->recusarCaptura(...),
          $this->errorEnvelope,
          $this->responseRenderer,
          'Id da captura a ser recusada',
      ),
    ];
  }
}
