<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Venda;

use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Fetches the next available sale number. */
#[AsCommand(name: 'venda proximo-numero', description: 'Consulta o próximo número de venda disponível')]
final class ProximoNumeroCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly VendasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Fetches the next sale number and renders output or a normalized error. */
  public function __invoke(): int {
    return $this->commandExecutor->execute(
        function (): void {
          $this->jsonRenderer->render($this->client->getProximoNumeroVenda());
        },
    );
  }
}
