<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pessoa patch', description: 'Atualiza parcialmente uma pessoa')]
final class PatchCommand extends Command
{
    private readonly CommandExecutor $commandExecutor;


    public function __construct(
        private readonly PessoasClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
        parent::__construct();
    }


    protected function configure(): void {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID da pessoa')
            ->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Payload JSON da pessoa');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function () use ($input): void {
            $id = $input->getArgument('id');
            $this->jsonRenderer->render(
              $this->client->patchPessoa(
                is_string($id) ? $id : '',
                JsonPayload::object($input->getOption('json')),
              )
            );
          }
        );
    }


}
