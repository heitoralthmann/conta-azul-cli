<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Contrato;

use ContaAzulCli\Api\ContratosClient;
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

/** Lists contracts for a date interval, with optional filters. */
#[AsCommand(name: 'contrato list', description: 'Lista contratos por filtros e intervalo de datas')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly ContratosClient $client,
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

  /** Resolves the interval, lists contracts, and renders output or an error. */
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
      int $tamanhoPagina = 10,
      #[Option(name: 'busca-textual', description: 'Busca textual pelo nome do contrato')]
      string|null $buscaTextual = null,
      #[Option(name: 'cliente-id', description: 'Filtra pelo ID do cliente')]
      string|null $clienteId = null,
      #[Option(
          name: 'campo-ordenado-ascendente',
          description: 'Campo para ordenação ascendente (DATA_INICIO, DATA_FIM)',
      )]
      string|null $campoOrdenadoAscendente = null,
      #[Option(
          name: 'campo-ordenado-descendente',
          description: 'Campo para ordenação descendente (DATA_INICIO, DATA_FIM); ignorado se a ascendente vier junto',
      )]
      string|null $campoOrdenadoDescendente = null,
  ): int {
    $filters = $this->filters($buscaTextual, $clienteId, $campoOrdenadoAscendente, $campoOrdenadoDescendente);

    return $this->commandExecutor->execute(
        function () use ($dataInicio, $dataFim, $pagina, $tamanhoPagina, $filters): void {
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

          $this->jsonRenderer->render(
              $this->client->listContratos($inicio, $fim, $pagination->page(), $pagination->pageSize(), $filters),
          );
        },
    );
  }

  /**
   * Builds the query filter map from the CLI options that were actually informed.
   *
   * @return array<string, string>
   */
  private function filters(
      string|null $buscaTextual,
      string|null $clienteId,
      string|null $campoOrdenadoAscendente,
      string|null $campoOrdenadoDescendente,
  ): array {
    $filters = [];

    if ($buscaTextual !== null && $buscaTextual !== '') {
      $filters['busca_textual'] = $buscaTextual;
    }

    if ($clienteId !== null && $clienteId !== '') {
      $filters['cliente_id'] = $clienteId;
    }

    if ($campoOrdenadoAscendente !== null && $campoOrdenadoAscendente !== '') {
      $filters['campo_ordenado_ascendente'] = $campoOrdenadoAscendente;
    }

    if ($campoOrdenadoDescendente !== null && $campoOrdenadoDescendente !== '') {
      $filters['campo_ordenado_descendente'] = $campoOrdenadoDescendente;
    }

    return $filters;
  }
}
