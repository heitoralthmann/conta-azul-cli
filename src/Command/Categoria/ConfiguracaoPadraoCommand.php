<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Categoria;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Fetches the de-para between financial operations and their default categories. */
#[AsCommand(
    name: 'categoria configuracao-padrao',
    description: 'Obtém o de-para padrão de categorias por operação financeira',
)]
final class ConfiguracaoPadraoCommand extends Command
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

  /** Fetches the default category mapping and renders output or a normalized error. */
  public function __invoke(
      #[Option(name: 'sugestao-padrao', description: 'Inclui a sugestão padrão de categoria em cada item')]
      bool $sugestaoPadrao = true,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($sugestaoPadrao): void {
          $this->responseRenderer->render($this->client->getConfiguracaoPadraoCategorias($sugestaoPadrao));
        },
    );
  }
}
