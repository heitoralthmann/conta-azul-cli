<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Replaces a person using a JSON payload. */
#[AsCommand(name: 'pessoa update', description: 'Atualiza integralmente uma pessoa')]
final class UpdateCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Parses input, updates the person, and renders output or an error. */
  public function __invoke(
      #[Argument(description: 'ID da pessoa')]
      string $id,
      #[Option(description: 'Payload JSON da pessoa')]
      string|null $json = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id, $json): void {
          $this->responseRenderer->render($this->client->updatePessoa($id, JsonPayload::object($json)));
        },
    );
  }
}
