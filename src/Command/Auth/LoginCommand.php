<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Auth;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'auth login', description: 'Autentica com a Conta Azul via OAuth2 (abre navegador)')]
final class LoginCommand extends Command
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly CallbackServer $callbackServer,
        private readonly ErrorEnvelope $errorEnvelope,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $authUrl = $this->authManager->startLoginFlow();
            $output->writeln("Abra este URL no seu navegador:\n");
            $output->writeln($authUrl);
            $output->writeln("\nAguardando callback OAuth na porta 9876...");

            $state = $this->authManager->getPendingState() ?? '';
            $code = $this->callbackServer->waitForCallback($state);

            $this->authManager->completeLoginFlow($code);
            $output->writeln("\nAutenticado com sucesso!");

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
