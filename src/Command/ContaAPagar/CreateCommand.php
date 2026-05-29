<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAPagar;

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

#[AsCommand(name: 'conta-a-pagar create', description: 'Cria uma conta a pagar')]
final class CreateCommand extends Command
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
        $this
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Payload JSON da conta a pagar')
            ->addOption('poll-timeout', null, InputOption::VALUE_REQUIRED, 'Timeout de polling em segundos', '60')
            ->addOption('no-wait', null, InputOption::VALUE_NONE, 'Retorna imediatamente sem aguardar confirmação assíncrona');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $jsonOption = $input->getOption('json');
            if ($jsonOption === null || $jsonOption === '') {
                throw new CliException(ErrorKind::ClientError, false, 'A opção --json é obrigatória.');
            }

            try {
                $payload = json_decode((string) $jsonOption, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new CliException(ErrorKind::ClientError, false, 'JSON inválido: ' . $e->getMessage(), previous: $e);
            }

            $pollTimeout = (int) $input->getOption('poll-timeout');
            $noWait = (bool) $input->getOption('no-wait');

            $this->jsonRenderer->render($this->client->createContaAPagar($payload, $pollTimeout, $noWait));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
