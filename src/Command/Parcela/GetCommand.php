<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Fetches one financial installment by identifier. */
#[AsCommand(name: 'parcela get', description: 'Obtém uma parcela pelo ID')]
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

  /** Fetches the installment and renders a normalized error on failure. */
  public function __invoke(
      #[Argument(description: 'ID da parcela')]
      string $id,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id): void {
          $this->responseRenderer->render($this->client->getParcela($id));
        },
    );
  }
}
