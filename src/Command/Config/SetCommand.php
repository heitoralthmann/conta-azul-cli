<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\ConfigKeys;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

use function implode;
use function strtoupper;
use function trim;

/** Writes one variable into the environment file that is currently in effect. */
#[AsCommand(
    name: 'config set',
    description: 'Grava uma variável no arquivo de configuração em vigor',
    help: <<<'HELP'
    Insere ou substitui a variável preservando comentários, linhas em branco e
    a ordem do arquivo, e reaplica a permissão 0600.

    Este comando grava no arquivo que estiver valendo agora — use
    "ca config path" para ver qual é. Já "ca config init" sempre grava em
    ~/.config/conta-azul-cli/.env, mesmo quando outro candidato está valendo.

    Um valor vazio equivale a não definir a variável: o CLI trata variável
    vazia como ausente e cai no default compilado.
    HELP,
)]
final class SetCommand extends Command
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

  /** Upserts the variable and reports the file it was written to. */
  public function __invoke(
      #[Argument(description: 'Nome da variável (ex.: CA_CLIENT_ID)')]
      string $chave,
      #[Argument(description: 'Valor a gravar; vazio equivale a não definir a variável')]
      string $valor,
  ): int {
    return $this->executor->execute(
        function () use ($chave, $valor): void {
          // Accepting lower case costs nothing and spares the operator a
          // second attempt; the file always ends up with the canonical name.
          $name = strtoupper(trim($chave));
          if (! ConfigKeys::isKnown($name)) {
            throw new ConfigException(
                'Variável desconhecida: ' . $chave . '. Variáveis aceitas: '
                    . implode(', ', ConfigKeys::names()) . '.',
            );
          }

          $path = $this->locator->locate()->path();
          if ($path === null) {
            throw new ConfigException(
                'Nenhum arquivo de configuração encontrado. Execute "ca config init" para criar '
                    . '~/.config/conta-azul-cli/.env, ou aponte '
                    . ConfigFileLocator::OVERRIDE_VARIABLE . ' para um arquivo existente.',
            );
          }

          $this->writer->upsert($path, $name, $valor);

          // The value is deliberately absent from the response: this command
          // is the usual way a client secret reaches the file, and its output
          // ends up in terminal scrollback and CI logs.
          $this->responseRenderer->render(
              [
                'arquivo' => $path,
                'chave'   => $name,
              ],
          );
        },
    );
  }
}
