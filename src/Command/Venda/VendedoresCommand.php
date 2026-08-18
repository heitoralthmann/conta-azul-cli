<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Venda;

use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Lists the sellers registered in Conta Azul. */
#[AsCommand(name: 'venda vendedores', description: 'Lista os vendedores cadastrados')]
final class VendedoresCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly VendasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Lists sellers and renders output or a normalized error. */
  public function __invoke(): int {
    return $this->commandExecutor->execute(
        function (): void {
          $this->jsonRenderer->render($this->client->listVendedores());
        },
    );
  }
}
