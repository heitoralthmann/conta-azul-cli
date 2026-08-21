<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Config;

use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Reports which environment file the CLI reads, and why. */
#[AsCommand(
    name: 'config path',
    description: 'Mostra qual arquivo de configuração o CLI usa e por quê',
    help: <<<'HELP'
    Lista o caminho resolvido e todos os candidatos, na ordem de precedência,
    com o motivo de cada um ter sido usado ou descartado. O primeiro candidato
    que existir vence; nada é mesclado.

    Candidatos, em ordem:
      1. o arquivo indicado por CA_CLI_ENV_FILE;
      2. o .env na raiz do projeto (pulado quando o CLI roda dentro de um PHAR);
      3. ~/.config/conta-azul-cli/.env.
    HELP,
)]
final class PathCommand extends Command
{
  private readonly ConfigCommandExecutor $executor;

  /** Creates the command with the search path and output collaborators. */
  public function __construct(
      private readonly ConfigFileLocator $locator,
      ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      ConfigCommandExecutor|null $executor = null,
  ) {
    $this->executor = $executor ?? new ConfigCommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Renders the resolved file and the full audit trail behind it. */
  public function __invoke(): int {
    return $this->executor->execute(
        function (): void {
          $resolution = $this->locator->locate();

          $this->responseRenderer->render(
              [
                'arquivo'    => $resolution->path(),
                'candidatos' => $resolution->toArray(),
              ],
          );
        },
    );
  }
}
