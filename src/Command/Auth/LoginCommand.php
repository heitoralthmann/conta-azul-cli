<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Auth;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackCertificateProvisioner;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\ErrorEnvelope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/** Runs the browser-based OAuth login flow. */
#[AsCommand(name: 'auth login', description: 'Autentica com a Conta Azul via OAuth2 (abre navegador)')]
final class LoginCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its authentication collaborators. */
  public function __construct(
      private readonly AuthManager $authManager,
      private readonly CallbackCertificateProvisioner $certificates,
      private readonly Configuration $config,
      private readonly ErrorEnvelope $errorEnvelope,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Starts OAuth, waits for the local callback, and stores the token. */
  public function __invoke(OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($output): void {
          // Certificates before the URL: the first login may prompt to trust
          // the local CA, and the operator should not race the browser past that.
          $tls     = $this->certificates->ensure($this->config);
          $authUrl = $this->authManager->startLoginFlow();
          $output->writeln("Abra este URL no seu navegador:\n");
          $output->writeln($authUrl);
          $output->writeln("\nAguardando callback OAuth na porta 9876...");

          $state = $this->authManager->getPendingState() ?? '';
          $code  = (new CallbackServer(
              timeoutSeconds: $this->config->callbackTimeout,
              certFile: $tls->certFile,
              keyFile: $tls->keyFile,
          ))->waitForCallback($state);

          $this->authManager->completeLoginFlow($code);
          $output->writeln("\nAutenticado com sucesso!");
        },
    );
  }
}
