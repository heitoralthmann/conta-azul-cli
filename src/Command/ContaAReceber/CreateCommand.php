<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAReceber;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\AsyncOptions;
use ContaAzulCli\Command\Support\CommandExecutor;
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
    private readonly CommandExecutor $commandExecutor;


    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
        parent::__construct();
    }


    protected function configure(): void {
        $this
            ->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Payload JSON da conta a receber');
        AsyncOptions::configure($this);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function () use ($input): void {
            $payload = JsonPayload::object($input->getOption('json'));
            $asyncOptions = AsyncOptions::fromInput($input);

            $this->jsonRenderer->render(
              $this->client->createContaAReceber($payload, $asyncOptions->pollTimeout(), $asyncOptions->noWait()),
            );
          }
        );
    }


}
