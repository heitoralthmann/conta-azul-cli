<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Fetches a person through its legacy identifier. */
#[AsCommand(name: 'pessoa legado', description: 'Busca uma pessoa por ID legado')]
final class LegadoCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Fetches the legacy person and renders output or an error. */
  public function __invoke(
      #[Argument(description: 'ID legado da pessoa')]
      string $id,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id): void {
          $this->jsonRenderer->render($this->client->getPessoaLegado($id));
        },
    );
  }
}
