<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Command\Auth\LoginCommand;
use ContaAzulCli\Command\Auth\LogoutCommand;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Output\ErrorEnvelope;
use Symfony\Component\Console\Command\Command;

/** Registers authentication commands and owns their construction. */
final class AuthCommandModule implements CommandModuleInterface
{
  /** Connects authentication services used by login and logout commands. */
  public function __construct(
      private readonly AuthManager $authManager,
      private readonly CallbackServer $callbackServer,
      private readonly ErrorEnvelope $errorEnvelope,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new LoginCommand($this->authManager, $this->callbackServer, $this->errorEnvelope),
      new LogoutCommand($this->authManager),
    ];
  }
}
