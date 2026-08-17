<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Registers payment for one financial installment. */
#[AsCommand(name: 'parcela baixar', description: 'Registra a baixa (pagamento) de uma parcela')]
final class BaixarCommand extends Command
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

  /** Validates payment input, invokes the API, and renders its result. */
  public function __invoke(
      #[Argument(description: 'ID da parcela')]
      string $id,
      #[Option(description: 'Valor da baixa (ex: 100.50)')]
      string|null $valor = null,
      #[Option(description: 'Data da baixa no formato YYYY-MM-DD')]
      string|null $data = null,
      #[Option(name: 'poll-timeout', description: 'Timeout de polling em segundos')]
      int $pollTimeout = 60,
      #[Option(name: 'no-wait', description: 'Retorna imediatamente sem aguardar confirmação assíncrona')]
      bool $noWait = false,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id, $valor, $data, $pollTimeout, $noWait): void {
          if ($valor === null || $valor === '') {
              throw new CliException(
                  ErrorKind::ClientError,
                  false,
                  'A opção --valor é obrigatória.',
              );
          }

          if ($data === null || $data === '') {
              throw new CliException(
                  ErrorKind::ClientError,
                  false,
                  'A opção --data é obrigatória.',
              );
          }

          $payload = [
            'valor' => (float) $valor,
            'data'  => $data,
          ];

          $this->jsonRenderer->render($this->client->baixarParcela($id, $payload, $pollTimeout, $noWait));
        },
    );
  }
}
