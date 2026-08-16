<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pessoa create', description: 'Cria uma pessoa')]
final class CreateCommand extends Command
{


    public function __construct(
        private readonly PessoasClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
    ) {
        parent::__construct();
    }


    protected function configure(): void {
        $this->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Payload JSON da pessoa');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $this->jsonRenderer->render($this->client->createPessoa(JsonPayload::object($input->getOption('json'))));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
