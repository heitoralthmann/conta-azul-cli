<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Captura;

use ContaAzulCli\Api\CapturaClient;
use ContaAzulCli\Api\PageSizeRule;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function trim;

/** Checks the processing status of uploaded documents and their captures. */
#[AsCommand(name: 'captura status', description: 'Consulta o status de documentos e das extrações (Captura)')]
final class StatusCommand extends Command
{
  /**
   * Largest number of document ids the endpoint accepts in one call.
   *
   * Above this it answers `400` ("O campo 'ids' não pode conter mais de 20
   * itens"). Measured against production on 2026-08-19.
   */
  private const int MAX_IDS = 20;

  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly CapturaClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Parses the comma-separated ids, queries status, and renders output or an error. */
  public function __invoke(
      #[Option(description: 'IDs dos documentos a consultar, separados por vírgula (máximo 20)')]
      string|null $ids = null,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página (1 a 20)')]
      int $tamanhoPagina = 10,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($ids, $pagina, $tamanhoPagina): void {
          $idList = array_values(array_filter(array_map(trim(...), explode(',', (string) $ids))));
          if ($idList === []) {
            throw new CliException(ErrorKind::ClientError, false, 'A opção --ids é obrigatória.');
          }

          if (count($idList) > self::MAX_IDS) {
            throw new CliException(
                ErrorKind::ClientError,
                false,
                'A opção --ids aceita no máximo ' . self::MAX_IDS . ' ids; foram informados ' . count($idList) . '.',
            );
          }

          $pagination = PaginationOptions::fromValues(
              $pagina,
              $tamanhoPagina,
              $this->paginationValidator,
              PaginationValidator::CAPTURA_MAX_SIZE,
              PageSizeRule::AnySizeUpToMax,
          );

          $this->responseRenderer->render(
              $this->client->statusDocumentos($idList, $pagination->page(), $pagination->pageSize()),
          );
        },
    );
  }
}
