<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Financeiro;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'financeiro alteracoes', description: 'Lista alterações de eventos financeiros desde uma data (útil para reconciliação)')]
final class AlteracoesCommand extends Command
{
    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'desde',
            null,
            InputOption::VALUE_REQUIRED,
            'Data/hora de início no formato ISO 8601 (ex: 2026-05-26T00:00:00-03:00)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $desde = $input->getOption('desde');
            if ($desde === null || $desde === '') {
                throw new CliException(ErrorKind::ClientError, false, 'A opção --desde é obrigatória. Use formato ISO 8601, ex: 2026-05-26T00:00:00-03:00');
            }
            $this->jsonRenderer->render($this->client->getAlteracoes((string) $desde));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
