<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Auth;

use ContaAzulCli\Auth\AuthManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth logout', description: 'Remove as credenciais salvas localmente')]
final class LogoutCommand extends Command
{
    public function __construct(private readonly AuthManager $authManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->authManager->logout();
        $output->writeln('Sessão encerrada. Execute "ca auth login" para autenticar novamente.');

        return Command::SUCCESS;
    }
}
