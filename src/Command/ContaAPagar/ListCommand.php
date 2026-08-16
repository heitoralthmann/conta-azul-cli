<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAPagar;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conta-a-pagar list', description: 'Lista contas a pagar por intervalo de vencimento')]
final class ListCommand extends Command
{


    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        private readonly PaginationValidator $paginationValidator,
        private readonly WarningEnvelope $warningEnvelope,
        private readonly PeriodoPadrao $periodoPadrao,
    ) {
        parent::__construct();
    }


    protected function configure(): void {
        $this
            ->addOption('data-vencimento-de', NULL, InputOption::VALUE_REQUIRED, 'Vencimento inicial (YYYY-MM-DD). Padrão: primeiro dia do mês corrente')
            ->addOption('data-vencimento-ate', NULL, InputOption::VALUE_REQUIRED, 'Vencimento final (YYYY-MM-DD). Padrão: último dia do mês corrente')
            ->addOption('pagina', NULL, InputOption::VALUE_REQUIRED, 'Número da página', '1')
            ->addOption('tamanho-pagina', NULL, InputOption::VALUE_REQUIRED, 'Itens por página', '50');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $deRaw  = $input->getOption('data-vencimento-de');
            $ateRaw = $input->getOption('data-vencimento-ate');

            $de  = is_string($deRaw) && $deRaw !== '' ? $deRaw : $this->periodoPadrao->primeiroDia();
            $ate = is_string($ateRaw) && $ateRaw !== '' ? $ateRaw : $this->periodoPadrao->ultimoDia();

            // A API exige o intervalo; um default silencioso esconderia o
            // recorte de quem lê só o stdout.
            if ($de !== $deRaw || $ate !== $ateRaw) {
                $this->warningEnvelope->renderToStderr(
                  "Intervalo de vencimento não informado por completo; usando {$de} a {$ate}. ".'Use --data-vencimento-de e --data-vencimento-ate para definir outro.',
                );
            }

            $paginaRaw        = $input->getOption('pagina');
            $tamanhoPaginaRaw = $input->getOption('tamanho-pagina');
            $pagina           = is_numeric($paginaRaw) ? (int) $paginaRaw : 1;
            $tamanhoPagina    = is_numeric($tamanhoPaginaRaw) ? (int) $tamanhoPaginaRaw : 50;
            $this->paginationValidator->validatePageSize($tamanhoPagina);

            $this->jsonRenderer->render(
              $this->client->listContasAPagar($de, $ate, $pagina, $tamanhoPagina),
            );

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
