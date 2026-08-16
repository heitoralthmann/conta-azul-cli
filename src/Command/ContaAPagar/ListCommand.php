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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

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

  /** Declares due-date and shared pagination options. */
  protected function configure(): void {
    $this
          ->addOption(
              'data-vencimento-de',
              null,
              InputOption::VALUE_REQUIRED,
              'Vencimento inicial (YYYY-MM-DD). Padrão: primeiro dia do mês corrente',
          )
          ->addOption(
              'data-vencimento-ate',
              null,
              InputOption::VALUE_REQUIRED,
              'Vencimento final (YYYY-MM-DD). Padrão: último dia do mês corrente',
          );
    PaginationOptions::configure($this);
  }

  /** Resolves dates, lists payables, and renders output or an error. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $deRaw  = $input->getOption('data-vencimento-de');
          $ateRaw = $input->getOption('data-vencimento-ate');

          $de  = is_string($deRaw) && $deRaw !== '' ? $deRaw : $this->periodoPadrao->primeiroDia();
          $ate = is_string($ateRaw) && $ateRaw !== '' ? $ateRaw : $this->periodoPadrao->ultimoDia();

          // A API exige o intervalo; um default silencioso esconderia o
          // recorte de quem lê só o stdout.
          if ($de !== $deRaw || $ate !== $ateRaw) {
              $this->warningEnvelope->renderToStderr(
                  'Intervalo de vencimento não informado por completo; usando ' . $de . ' a ' . $ate . '. '
                      . 'Use --data-vencimento-de e --data-vencimento-ate para definir outro.',
              );
          }

          $pagination = PaginationOptions::fromInput($input, $this->paginationValidator);

          $this->jsonRenderer->render(
              $this->client->listContasAPagar($de, $ate, $pagination->page(), $pagination->pageSize()),
          );
        },
    );
  }
}
