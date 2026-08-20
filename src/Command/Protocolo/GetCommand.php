<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Protocolo;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Retrieves the status of an asynchronous operation by protocol ID. */
#[AsCommand(name: 'protocolo get', description: 'Consulta o status de uma escrita assíncrona pelo protocol ID')]
final class GetCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Fetches protocol status and renders output or a normalized error. */
  public function __invoke(
      #[Argument(description: 'Protocol ID retornado pela operação assíncrona')]
      string $id,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id): void {
          $this->responseRenderer->render($this->client->getProtocolo($id));
        },
    );
  }
}
