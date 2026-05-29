<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaFinanceira;

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

#[AsCommand(name: 'conta-financeira saldo', description: 'Obtém o saldo de uma conta financeira')]
final class SaldoCommand extends Command
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
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'ID da conta financeira');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $input->getOption('id');
            if ($id === null || $id === '') {
                throw new CliException(ErrorKind::ClientError, false, 'A opção --id é obrigatória.');
            }
            $this->jsonRenderer->render($this->client->getSaldoContaFinanceira((string) $id));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
