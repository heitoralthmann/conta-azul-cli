<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Auth;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Runs the browser-based OAuth login flow. */
#[AsCommand(name: 'auth login', description: 'Autentica com a Conta Azul via OAuth2 (abre navegador)')]
final class LoginCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its authentication collaborators. */
  public function __construct(
      private readonly AuthManager $authManager,
      private readonly CallbackServer $callbackServer,
      private readonly ErrorEnvelope $errorEnvelope,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Starts OAuth, waits for the local callback, and stores the token. */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    return $this->commandExecutor->execute(
        function () use ($output): void {
          $authUrl = $this->authManager->startLoginFlow();
          $output->writeln("Abra este URL no seu navegador:\n");
          $output->writeln($authUrl);
          $output->writeln("\nAguardando callback OAuth na porta 9876...");

          $state = $this->authManager->getPendingState() ?? '';
          $code  = $this->callbackServer->waitForCallback($state);

          $this->authManager->completeLoginFlow($code);
          $output->writeln("\nAutenticado com sucesso!");
        },
    );
  }
}
