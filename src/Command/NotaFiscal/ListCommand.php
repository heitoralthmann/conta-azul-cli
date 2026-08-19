<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\NotaFiscal;

use ContaAzulCli\Api\NotasFiscaisClient;
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

/** Lists product invoices (NFe) already issued, for a date interval. */
#[AsCommand(name: 'nota-fiscal list', description: 'Lista notas fiscais de produto (NFe) por filtros')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly NotasFiscaisClient $client,
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

  /** Resolves the interval, lists invoices, and renders output or an error. */
  public function __invoke(
      #[Option(
          name: 'data-inicial',
          description: 'Início do intervalo (YYYY-MM-DD). Padrão: 15 dias atrás. '
              . 'Intervalo máximo aceito pela API: 15 dias',
      )]
      string|null $dataInicial = null,
      #[Option(
          name: 'data-final',
          description: 'Fim do intervalo (YYYY-MM-DD). Padrão: hoje',
      )]
      string|null $dataFinal = null,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página (10, 20, 50 ou 100)')]
      int $tamanhoPagina = 10,
      #[Option(name: 'documento-tomador', description: 'Filtra pelo documento (CPF/CNPJ) do tomador')]
      string|null $documentoTomador = null,
      #[Option(name: 'numero-nota', description: 'Filtra pelo número da nota fiscal')]
      string|null $numeroNota = null,
      #[Option(name: 'id-venda', description: 'Filtra pelo ID da venda')]
      string|null $idVenda = null,
  ): int {
    $filters = $this->filters($documentoTomador, $numeroNota, $idVenda);

    return $this->commandExecutor->execute(
        function () use ($dataInicial, $dataFinal, $pagina, $tamanhoPagina, $filters): void {
          $inicio = $dataInicial !== null && $dataInicial !== ''
              ? $dataInicial
              : $this->periodoPadrao->inicioUltimos15Dias();
          $fim    = $dataFinal !== null && $dataFinal !== ''
              ? $dataFinal
              : $this->periodoPadrao->hoje();

          // A API exige o intervalo e limita a 15 dias; um default silencioso
          // esconderia o recorte de quem lê só o stdout.
          if ($inicio !== $dataInicial || $fim !== $dataFinal) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo não informado por completo; usando ' . $inicio . ' a ' . $fim . '. '
                      . 'Use --data-inicial e --data-final para definir outro (máx. 15 dias).',
              );
          }

          $pagination = PaginationOptions::fromValues(
              $pagina,
              $tamanhoPagina,
              $this->paginationValidator,
              PaginationValidator::CAPPED_MAX_SIZE,
          );

          $this->jsonRenderer->render(
              $this->client->listNotasFiscais($inicio, $fim, $pagination->page(), $pagination->pageSize(), $filters),
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
      string|null $documentoTomador,
      string|null $numeroNota,
      string|null $idVenda,
  ): array {
    $filters = [];

    if ($documentoTomador !== null && $documentoTomador !== '') {
      $filters['documento_tomador'] = $documentoTomador;
    }

    if ($numeroNota !== null && $numeroNota !== '') {
      $filters['numero_nota'] = $numeroNota;
    }

    if ($idVenda !== null && $idVenda !== '') {
      $filters['id_venda'] = $idVenda;
    }

    return $filters;
  }
}
