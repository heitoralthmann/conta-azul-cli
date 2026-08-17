<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Financeiro;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Lists financial event changes for reconciliation. */
#[AsCommand(
    name: 'financeiro alteracoes',
    description: 'Lista alterações de eventos financeiros num intervalo (útil para reconciliação)',
)]
final class AlteracoesCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly WarningEnvelope $warningEnvelope,
      private readonly PeriodoPadrao $periodoPadrao,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Resolves the interval, warns about defaults, and fetches changes. */
  public function __invoke(
      #[Option(
          name: 'data-inicio',
          description: 'Início em ISO 8601 sem timezone (ex: 2026-08-01T00:00:00). Padrão: início do mês corrente',
      )]
      string|null $dataInicio = null,
      #[Option(
          name: 'data-fim',
          description: 'Fim em ISO 8601 sem timezone (ex: 2026-08-31T23:59:59). Padrão: fim do mês corrente',
      )]
      string|null $dataFim = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($dataInicio, $dataFim): void {
          $inicio = $dataInicio !== null && $dataInicio !== '' ? $dataInicio : $this->periodoPadrao->primeiroInstante();
          $fim    = $dataFim !== null && $dataFim !== ''       ? $dataFim    : $this->periodoPadrao->ultimoInstante();

          if ($inicio !== $dataInicio || $fim !== $dataFim) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo não informado por completo; usando ' . $inicio . ' a ' . $fim . '. '
                      . 'Use --data-inicio e --data-fim para definir outro.',
              );
          }

          $this->jsonRenderer->render($this->client->getAlteracoes($inicio, $fim));
        },
    );
  }
}
