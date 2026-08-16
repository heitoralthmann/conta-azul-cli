<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pessoa conta-conectada', description: 'Busca a empresa da conta conectada')]
final class ContaConectadaCommand extends Command
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


    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function (): void {
            $this->jsonRenderer->render($this->client->getContaConectada());
          }
        );
    }


}
