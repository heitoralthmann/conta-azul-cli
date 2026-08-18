<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Venda;

use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

use function base64_encode;

/** Fetches the PDF for one sale, identified by uuid or legacy id. */
#[AsCommand(name: 'venda imprimir', description: 'Gera o PDF de uma venda')]
final class ImprimirCommand extends Command
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

  /**
   * Fetches the PDF and renders its content or a normalized error.
   *
   * A resposta da API é binária (PDF), não JSON; para manter o contrato de
   * stdout do CLI, o conteúdo vai em base64 dentro do envelope.
   */
  public function __invoke(
      #[Argument(description: 'Uuid ou id legado da venda')]
      string $id,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id): void {
          $resposta = $this->client->imprimirVenda($id);

          $this->jsonRenderer->render(
              [
                'content_base64' => base64_encode($resposta['content']),
                'content_type'   => $resposta['contentType'],
              ],
          );
        },
    );
  }
}
