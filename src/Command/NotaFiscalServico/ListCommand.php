<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\NotaFiscalServico;

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

/** Lists service invoices (NFS-e) already issued, for a competence-date interval. */
#[AsCommand(name: 'nota-fiscal-servico list', description: 'Lista notas fiscais de serviço (NFS-e) por filtros')]
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

  /**
   * Resolves the interval, lists invoices, and renders output or an error.
   *
   * @param list<string> $ids
   * @param list<string> $idCliente
   * @param list<string> $status
   */
  public function __invoke(
      #[Option(
          name: 'data-competencia-de',
          description: 'Emissão inicial (YYYY-MM-DD). Padrão: 15 dias atrás. Intervalo máximo aceito pela API: 15 dias',
      )]
      string|null $dataCompetenciaDe = null,
      #[Option(
          name: 'data-competencia-ate',
          description: 'Emissão final (YYYY-MM-DD). Padrão: hoje',
      )]
      string|null $dataCompetenciaAte = null,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página (10, 20, 50 ou 100)')]
      int $tamanhoPagina = 10,
      #[Option(name: 'ids', description: 'Filtra por UUID(s) da nota fiscal de serviço (repetível)')]
      array $ids = [],
      #[Option(name: 'id-cliente', description: 'Filtra por UUID(s) de cliente (repetível)')]
      array $idCliente = [],
      #[Option(name: 'numero-venda', description: 'Filtra pelo número da venda')]
      int|null $numeroVenda = null,
      #[Option(name: 'numero-nfse-inicial', description: 'Número inicial da NFS-e')]
      int|null $numeroNfseInicial = null,
      #[Option(name: 'numero-nfse-final', description: 'Número final da NFS-e')]
      int|null $numeroNfseFinal = null,
      #[Option(name: 'numero-rps-inicial', description: 'Número inicial do RPS')]
      int|null $numeroRpsInicial = null,
      #[Option(name: 'numero-rps-final', description: 'Número final do RPS')]
      int|null $numeroRpsFinal = null,
      #[Option(name: 'status', description: 'Filtra por status (repetível): PENDENTE, EMITIDA, CANCELADA, etc.')]
      array $status = [],
      #[Option(name: 'tipo-negociacao', description: 'Filtra por tipo de negociação: VENDA ou CONTRATO')]
      string|null $tipoNegociacao = null,
  ): int {
    $filters = $this->filters(
        $ids,
        $idCliente,
        $numeroVenda,
        $numeroNfseInicial,
        $numeroNfseFinal,
        $numeroRpsInicial,
        $numeroRpsFinal,
        $status,
        $tipoNegociacao,
    );

    return $this->commandExecutor->execute(
        function () use ($dataCompetenciaDe, $dataCompetenciaAte, $pagina, $tamanhoPagina, $filters): void {
          $de  = $dataCompetenciaDe !== null && $dataCompetenciaDe !== ''
              ? $dataCompetenciaDe
              : $this->periodoPadrao->inicioUltimos15Dias();
          $ate = $dataCompetenciaAte !== null && $dataCompetenciaAte !== ''
              ? $dataCompetenciaAte
              : $this->periodoPadrao->hoje();

          // A API exige o intervalo e limita a 15 dias; um default silencioso
          // esconderia o recorte de quem lê só o stdout.
          if ($de !== $dataCompetenciaDe || $ate !== $dataCompetenciaAte) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo de competência não informado por completo; usando ' . $de . ' a ' . $ate . '. '
                      . 'Use --data-competencia-de e --data-competencia-ate para definir outro (máx. 15 dias).',
              );
          }

          $pagination = PaginationOptions::fromValues($pagina, $tamanhoPagina, $this->paginationValidator);

          $this->jsonRenderer->render(
              $this->client->listNotasFiscaisServico(
                  $de,
                  $ate,
                  $pagination->page(),
                  $pagination->pageSize(),
                  $filters,
              ),
          );
        },
    );
  }

  /**
   * Builds the query filter map from the CLI options that were actually informed.
   *
   * @param list<string> $ids
   * @param list<string> $idCliente
   * @param list<string> $status
   *
   * @return array<string, mixed>
   */
  private function filters(
      array $ids,
      array $idCliente,
      int|null $numeroVenda,
      int|null $numeroNfseInicial,
      int|null $numeroNfseFinal,
      int|null $numeroRpsInicial,
      int|null $numeroRpsFinal,
      array $status,
      string|null $tipoNegociacao,
  ): array {
    $filters = [];

    if ($ids !== []) {
      $filters['ids'] = $ids;
    }

    if ($idCliente !== []) {
      $filters['id_cliente'] = $idCliente;
    }

    if ($numeroVenda !== null) {
      $filters['numero_venda'] = $numeroVenda;
    }

    if ($numeroNfseInicial !== null) {
      $filters['numero_nfse_inicial'] = $numeroNfseInicial;
    }

    if ($numeroNfseFinal !== null) {
      $filters['numero_nfse_final'] = $numeroNfseFinal;
    }

    if ($numeroRpsInicial !== null) {
      $filters['numero_rps_inicial'] = $numeroRpsInicial;
    }

    if ($numeroRpsFinal !== null) {
      $filters['numero_rps_final'] = $numeroRpsFinal;
    }

    if ($status !== []) {
      $filters['status'] = $status;
    }

    if ($tipoNegociacao !== null && $tipoNegociacao !== '') {
      $filters['tipo_negociacao'] = $tipoNegociacao;
    }

    return $filters;
  }
}
