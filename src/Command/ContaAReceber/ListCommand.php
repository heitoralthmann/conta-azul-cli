<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAReceber;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conta-a-receber list', description: 'Lista contas a receber')]
final class ListCommand extends Command
{
    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        private readonly PaginationValidator $paginationValidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pagina', null, InputOption::VALUE_REQUIRED, 'Número da página', '1')
            ->addOption('tamanho-pagina', null, InputOption::VALUE_REQUIRED, 'Itens por página', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $paginaRaw        = $input->getOption('pagina');
            $tamanhoPaginaRaw = $input->getOption('tamanho-pagina');
            $pagina           = is_numeric($paginaRaw) ? (int) $paginaRaw : 1;
            $tamanhoPagina    = is_numeric($tamanhoPaginaRaw) ? (int) $tamanhoPaginaRaw : 50;
            $this->paginationValidator->validatePageSize($tamanhoPagina);
            $this->jsonRenderer->render($this->client->listContasAReceber($pagina, $tamanhoPagina));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
