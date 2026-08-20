<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Contrato;

use ContaAzulCli\Api\ContratosClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Fetches the next available contract number. */
#[AsCommand(name: 'contrato proximo-numero', description: 'Consulta o próximo número de contrato disponível')]
final class ProximoNumeroCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly ContratosClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Fetches the next contract number and renders output or a normalized error. */
  public function __invoke(): int {
    return $this->commandExecutor->execute(
        function (): void {
          $this->responseRenderer->render($this->client->getProximoNumeroContrato());
        },
    );
  }
}
