<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Partially updates a person using a JSON payload. */
#[AsCommand(name: 'pessoa patch', description: 'Atualiza parcialmente uma pessoa')]
final class PatchCommand extends Command
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

  /** Parses input, patches the person, and renders output or an error. */
  public function __invoke(
      #[Argument(description: 'ID da pessoa')]
      string $id,
      #[Option(description: 'Payload JSON da pessoa')]
      string|null $json = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id, $json): void {
          $this->jsonRenderer->render($this->client->patchPessoa($id, JsonPayload::object($json)));
        },
    );
  }
}
