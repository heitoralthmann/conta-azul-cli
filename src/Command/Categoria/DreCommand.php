<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Categoria;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Lists DRE (income statement) categories. */
#[AsCommand(name: 'categoria dre', description: 'Lista categorias DRE')]
final class DreCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Lists DRE categories and renders output or a normalized error. */
  public function __invoke(): int {
    return $this->commandExecutor->execute(
        function (): void {
          $this->jsonRenderer->render($this->client->listCategoriasDre());
        },
    );
  }
}
