<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAPagar;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Creates a payable from JSON and optionally waits for completion. */
#[AsCommand(name: 'conta-a-pagar create', description: 'Cria uma conta a pagar')]
final class CreateCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Creates the payable and renders output or a normalized error. */
  public function __invoke(
      #[Option(description: 'Payload JSON da conta a pagar')]
      string|null $json = null,
      #[Option(name: 'poll-timeout', description: 'Timeout de polling em segundos')]
      int $pollTimeout = 60,
      #[Option(name: 'no-wait', description: 'Retorna imediatamente sem aguardar confirmação assíncrona')]
      bool $noWait = false,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($json, $pollTimeout, $noWait): void {
          $payload = JsonPayload::object($json);

          $this->jsonRenderer->render($this->client->createContaAPagar($payload, $pollTimeout, $noWait));
        },
    );
  }
}
