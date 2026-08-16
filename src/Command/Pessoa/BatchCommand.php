<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Executes one supported bulk person operation. */
final class BatchCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /**
   * Creates a command for a fixed bulk operation.
   *
   * @param 'activate'|'deactivate'|'delete' $operation
   */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      string $name,
      private readonly string $operation,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct($name);

    $this->setDescription(
        match ($operation) {
          'activate' => 'Ativa pessoas em lote',
          'deactivate' => 'Inativa pessoas em lote',
          'delete' => 'Exclui pessoas em lote',
        },
    );
  }

  /** Declares the JSON payload option for the bulk operation. */
  protected function configure(): void
  {
    $this->addOption('json', null, InputOption::VALUE_REQUIRED, 'Payload JSON com a lista de uuids');
  }

  /** Parses the payload, executes the selected operation, and renders output. */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $payload = JsonPayload::object($input->getOption('json'));
          $result  = match ($this->operation) {
              'activate' => $this->client->activatePessoas($payload),
              'deactivate' => $this->client->deactivatePessoas($payload),
              'delete' => $this->client->deletePessoas($payload),
          };
            $this->jsonRenderer->render($result);
        },
    );
  }
}
