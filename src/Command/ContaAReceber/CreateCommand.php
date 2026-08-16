<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAReceber;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\AsyncOptions;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conta-a-receber create', description: 'Cria uma conta a receber')]
final class CreateCommand extends Command
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
            ->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Payload JSON da conta a receber');
        AsyncOptions::configure($this);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $payload = JsonPayload::object($input->getOption('json'));
            $asyncOptions = AsyncOptions::fromInput($input);

            $this->jsonRenderer->render(
              $this->client->createContaAReceber($payload, $asyncOptions->pollTimeout(), $asyncOptions->noWait()),
            );

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
