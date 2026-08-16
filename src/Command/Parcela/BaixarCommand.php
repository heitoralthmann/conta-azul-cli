<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'parcela baixar', description: 'Registra a baixa (pagamento) de uma parcela')]
final class BaixarCommand extends Command
{


    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
    ) {
        parent::__construct();
    }


    protected function configure(): void {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID da parcela')
            ->addOption('valor', NULL, InputOption::VALUE_REQUIRED, 'Valor da baixa (ex: 100.50)')
            ->addOption('data', NULL, InputOption::VALUE_REQUIRED, 'Data da baixa no formato YYYY-MM-DD')
            ->addOption('poll-timeout', NULL, InputOption::VALUE_REQUIRED, 'Timeout de polling em segundos', '60')
            ->addOption('no-wait', NULL, InputOption::VALUE_NONE, 'Retorna imediatamente sem aguardar confirmação assíncrona');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $rawId = $input->getArgument('id');
            $id    = is_string($rawId) ? $rawId : '';

            $valorOption = $input->getOption('valor');
            $dataOption  = $input->getOption('data');

            if (!is_string($valorOption) || $valorOption === '') {
                throw new CliException(
                  \ContaAzulCli\Error\ErrorKind::ClientError,
                  FALSE,
                  'A opção --valor é obrigatória.',
                );
            }
            if (!is_string($dataOption) || $dataOption === '') {
                throw new CliException(
                  \ContaAzulCli\Error\ErrorKind::ClientError,
                  FALSE,
                  'A opção --data é obrigatória.',
                );
            }

            $payload = [
                'valor' => (float) $valorOption,
                'data'  => $dataOption,
            ];

            $pollTimeoutRaw = $input->getOption('poll-timeout');
            $pollTimeout    = is_numeric($pollTimeoutRaw) ? (int) $pollTimeoutRaw : 60;
            $noWait         = (bool) $input->getOption('no-wait');

            $this->jsonRenderer->render($this->client->baixarParcela($id, $payload, $pollTimeout, $noWait));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
