<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Creates a person from a JSON payload. */
#[AsCommand(name: 'pessoa create', description: 'Cria uma pessoa')]
final class CreateCommand extends Command
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

  /** Parses the payload, creates the person, and renders output or an error. */
  public function __invoke(
      #[Option(description: 'Payload JSON da pessoa')]
      string|null $json = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($json): void {
          $this->responseRenderer->render($this->client->createPessoa(JsonPayload::object($json)));
        },
    );
  }
}
