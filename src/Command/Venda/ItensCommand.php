<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Venda;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Lists the items belonging to one sale. */
#[AsCommand(name: 'venda itens', description: 'Lista os itens de uma venda')]
final class ItensCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly VendasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Lists the sale's items and renders output or a normalized error. */
  public function __invoke(
      #[Argument(description: 'Uuid da venda')]
      string $idVenda,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página')]
      int $tamanhoPagina = 10,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($idVenda, $pagina, $tamanhoPagina): void {
          $pagination = PaginationOptions::fromValues($pagina, $tamanhoPagina, $this->paginationValidator);

          $this->responseRenderer->render(
              $this->client->listItensVenda($idVenda, $pagination->page(), $pagination->pageSize()),
          );
        },
    );
  }
}
