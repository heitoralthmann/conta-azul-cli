<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Transferencia;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Lists transfers between financial accounts for a date interval. */
#[AsCommand(
    name: 'transferencia list',
    description: 'Lista transferências entre contas financeiras por intervalo de datas',
)]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
      private readonly WarningEnvelope $warningEnvelope,
      private readonly PeriodoPadrao $periodoPadrao,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Resolves the interval, lists transfers, and renders output or an error. */
  public function __invoke(
      #[Option(
          name: 'data-inicio',
          description: 'Início do intervalo (YYYY-MM-DD). Padrão: primeiro dia do mês corrente',
      )]
      string|null $dataInicio = null,
      #[Option(
          name: 'data-fim',
          description: 'Fim do intervalo (YYYY-MM-DD). Padrão: último dia do mês corrente',
      )]
      string|null $dataFim = null,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página')]
      int $tamanhoPagina = 50,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($dataInicio, $dataFim, $pagina, $tamanhoPagina): void {
          $inicio = $dataInicio !== null && $dataInicio !== '' ? $dataInicio : $this->periodoPadrao->primeiroDia();
          $fim    = $dataFim !== null && $dataFim !== ''       ? $dataFim    : $this->periodoPadrao->ultimoDia();

          // A API exige o intervalo; um default silencioso esconderia o
          // recorte de quem lê só o stdout.
          if ($inicio !== $dataInicio || $fim !== $dataFim) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo não informado por completo; usando ' . $inicio . ' a ' . $fim . '. '
                      . 'Use --data-inicio e --data-fim para definir outro.',
              );
          }

          $pagination = PaginationOptions::fromValues($pagina, $tamanhoPagina, $this->paginationValidator);

          $this->responseRenderer->render(
              $this->client->listTransferencias($inicio, $fim, $pagination->page(), $pagination->pageSize()),
          );
        },
    );
  }
}
