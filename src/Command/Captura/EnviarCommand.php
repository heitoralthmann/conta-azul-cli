<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Captura;

use ContaAzulCli\Api\CapturaClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

use function is_readable;

/** Uploads one local file to the Captura AI for automatic data extraction. */
#[AsCommand(name: 'captura enviar', description: 'Envia um documento para a captura e extração de dados')]
final class EnviarCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly CapturaClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Validates the local file, sends it, and renders the result or a normalized error. */
  public function __invoke(
      #[Argument(description: 'Caminho do arquivo local (PDF, JPEG, PNG ou BMP; máximo de 10 MB)')]
      string $arquivo,
      #[Option(description: 'Descrição opcional do documento (máximo de 255 caracteres)')]
      string|null $descricao = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($arquivo, $descricao): void {
          if (! is_readable($arquivo)) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'Arquivo não encontrado ou sem permissão de leitura: ' . $arquivo,
            );
          }

          $this->jsonRenderer->render($this->client->enviarDocumento($arquivo, $descricao));
        },
    );
  }
}
