<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Categoria;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Lists financial categories using the shared pagination options. */
#[AsCommand(name: 'categoria list', description: 'Lista categorias financeiras')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Declares the shared pagination options. */
  protected function configure(): void {
    PaginationOptions::configure($this);
  }

  /** Lists categories and renders a normalized error on failure. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $pagination = PaginationOptions::fromInput($input, $this->paginationValidator);

          $this->jsonRenderer->render($this->client->listCategorias($pagination->page(), $pagination->pageSize()));
        },
    );
  }
}
