<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAPagar;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Lists payables for a due-date interval. */
#[AsCommand(name: 'conta-a-pagar list', description: 'Lista contas a pagar por intervalo de vencimento')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
      private readonly WarningEnvelope $warningEnvelope,
      private readonly PeriodoPadrao $periodoPadrao,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Resolves dates, lists payables, and renders output or an error. */
  public function __invoke(
      #[Option(
          name: 'data-vencimento-de',
          description: 'Vencimento inicial (YYYY-MM-DD). Padrão: primeiro dia do mês corrente',
      )]
      string|null $dataVencimentoDe = null,
      #[Option(
          name: 'data-vencimento-ate',
          description: 'Vencimento final (YYYY-MM-DD). Padrão: último dia do mês corrente',
      )]
      string|null $dataVencimentoAte = null,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página')]
      int $tamanhoPagina = 50,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($dataVencimentoDe, $dataVencimentoAte, $pagina, $tamanhoPagina): void {
          $de  = $dataVencimentoDe !== null && $dataVencimentoDe !== ''
              ? $dataVencimentoDe
              : $this->periodoPadrao->primeiroDia();
          $ate = $dataVencimentoAte !== null && $dataVencimentoAte !== ''
              ? $dataVencimentoAte
              : $this->periodoPadrao->ultimoDia();

          // A API exige o intervalo; um default silencioso esconderia o
          // recorte de quem lê só o stdout.
          if ($de !== $dataVencimentoDe || $ate !== $dataVencimentoAte) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo de vencimento não informado por completo; usando ' . $de . ' a ' . $ate . '. '
                      . 'Use --data-vencimento-de e --data-vencimento-ate para definir outro.',
              );
          }

          $pagination = PaginationOptions::fromValues($pagina, $tamanhoPagina, $this->paginationValidator);

          $this->jsonRenderer->render(
              $this->client->listContasAPagar($de, $ate, $pagination->page(), $pagination->pageSize()),
          );
        },
    );
  }
}
