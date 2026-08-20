<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\NotaFiscal;

use ContaAzulCli\Api\NotasFiscaisClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

use function base64_encode;

/** Fetches one product invoice (NFe) by access key. */
#[AsCommand(name: 'nota-fiscal get', description: 'Busca uma nota fiscal de produto (NFe) pela chave de acesso')]
final class GetCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly NotasFiscaisClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /**
   * Fetches the invoice and renders its content or a normalized error.
   *
   * A resposta da API é binária (XML ou ZIP), não JSON; para manter o
   * contrato de stdout do CLI, o conteúdo vai em base64 dentro do envelope.
   */
  public function __invoke(
      #[Argument(description: 'Chave de acesso da nota fiscal')]
      string $chave,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($chave): void {
          $resposta = $this->client->getNotaFiscalPorChave($chave);

          $this->responseRenderer->render(
              [
                'content_base64' => base64_encode($resposta['content']),
                'content_type'   => $resposta['contentType'],
              ],
          );
        },
    );
  }
}
