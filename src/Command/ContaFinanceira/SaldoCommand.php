<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaFinanceira;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Fetches the current balance for one financial account. */
#[AsCommand(name: 'conta-financeira saldo', description: 'Obtém o saldo de uma conta financeira')]
final class SaldoCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Validates the identifier, fetches the balance, and renders the result. */
  public function __invoke(
      #[Option(description: 'ID da conta financeira')]
      string|null $id = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id): void {
          if ($id === null || $id === '') {
              throw new CliException(ErrorKind::ClientError, false, 'A opção --id é obrigatória.');
          }

          $this->responseRenderer->render($this->client->getSaldoContaFinanceira($id));
        },
    );
  }
}
