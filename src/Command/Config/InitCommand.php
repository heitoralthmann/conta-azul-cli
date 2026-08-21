<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\EnvFileTemplate;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

use function file_exists;

/** Creates the user-level environment file from the embedded template. */
#[AsCommand(
    name: 'config init',
    description: 'Cria ~/.config/conta-azul-cli/.env a partir do modelo embutido',
    help: <<<'HELP'
    Escreve o arquivo do usuário com permissão 0600, dentro de um diretório
    0700, e nunca sobrescreve um arquivo existente sem --force.

    Este comando sempre grava em ~/.config/conta-azul-cli/.env, mesmo que outro
    candidato esteja valendo agora — essa é exatamente a razão de ele existir:
    criar o arquivo que torna o CLI utilizável fora do diretório do projeto.
    Já "ca config set" grava no arquivo que estiver valendo, seja qual for.
    Use "ca config path" para ver qual é.
    HELP,
)]
final class InitCommand extends Command
{
  private readonly ConfigCommandExecutor $executor;

  /** Creates the command with the search path, the writer, and output collaborators. */
  public function __construct(
      private readonly ConfigFileLocator $locator,
      private readonly EnvFileWriter $writer,
      ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      ConfigCommandExecutor|null $executor = null,
  ) {
    $this->executor = $executor ?? new ConfigCommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Writes the template to the user-level file and reports where it landed. */
  public function __invoke(
      #[Option(description: 'Sobrescreve o arquivo do usuário se ele já existir')]
      bool $force = false,
  ): int {
    return $this->executor->execute(
        function () use ($force): void {
          $path = $this->locator->userFile();
          if ($path === null) {
            throw new ConfigException(
                'Não foi possível determinar o diretório home do usuário. Crie o arquivo manualmente e '
                    . 'aponte ' . ConfigFileLocator::OVERRIDE_VARIABLE . ' para ele.',
            );
          }

          if ($force === false && file_exists($path)) {
            throw new ConfigException(
                'O arquivo ' . $path . ' já existe. Use --force para sobrescrevê-lo, ou '
                    . '"ca config set" para alterar apenas uma variável.',
            );
          }

          $this->writer->create($path, EnvFileTemplate::CONTENTS);

          $this->responseRenderer->render(
              [
                'arquivo'       => $path,
                'proximo_passo' => 'Defina as credenciais com "ca config set CA_CLIENT_ID <valor>" '
                    . 'e "ca config set CA_CLIENT_SECRET <valor>".',
              ],
          );
        },
    );
  }
}
