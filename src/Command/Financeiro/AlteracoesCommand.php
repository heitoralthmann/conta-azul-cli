<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Financeiro;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Lists financial event changes for reconciliation. */
#[AsCommand(name: 'financeiro alteracoes', description: 'Lista alterações de eventos financeiros num intervalo (útil para reconciliação)')]
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
        ?CommandExecutor $commandExecutor=NULL,
    ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
    parent::__construct();
  }


  /** Declares optional ISO-8601 interval boundaries. */
  protected function configure(): void {
    $this
          ->addOption(
            'data-inicio',
            NULL,
            InputOption::VALUE_REQUIRED,
            'Início em ISO 8601 sem timezone (ex: 2026-08-01T00:00:00). Padrão: início do mês corrente',
          )
          ->addOption(
            'data-fim',
            NULL,
            InputOption::VALUE_REQUIRED,
            'Fim em ISO 8601 sem timezone (ex: 2026-08-31T23:59:59). Padrão: fim do mês corrente',
          );
  }


  /** Resolves the interval, warns about defaults, and fetches changes. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
      function () use ($input): void {
        $inicioRaw = $input->getOption('data-inicio');
        $fimRaw    = $input->getOption('data-fim');

        $inicio = is_string($inicioRaw) && $inicioRaw !== '' ? $inicioRaw : $this->periodoPadrao->primeiroInstante();
        $fim    = is_string($fimRaw) && $fimRaw !== '' ? $fimRaw : $this->periodoPadrao->ultimoInstante();

        if ($inicio !== $inicioRaw || $fim !== $fimRaw) {
            $this->warningEnvelope->renderToStderr(
              "Intervalo não informado por completo; usando {$inicio} a {$fim}. " . 'Use --data-inicio e --data-fim para definir outro.',
            );
        }

        $this->jsonRenderer->render($this->client->getAlteracoes($inicio, $fim));
      }
    );
  }


}
