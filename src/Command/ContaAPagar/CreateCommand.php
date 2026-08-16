<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaAPagar;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\AsyncOptions;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Creates a payable from JSON and optionally waits for completion. */
#[AsCommand(name: 'conta-a-pagar create', description: 'Cria uma conta a pagar')]
final class CreateCommand extends Command
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

  /** Declares the JSON payload and asynchronous options. */
  protected function configure(): void {
    $this
          ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Payload JSON da conta a pagar');
    AsyncOptions::configure($this);
  }

  /** Creates the payable and renders output or a normalized error. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $payload      = JsonPayload::object($input->getOption('json'));
          $asyncOptions = AsyncOptions::fromInput($input);

          $this->jsonRenderer->render(
              $this->client->createContaAPagar($payload, $asyncOptions->pollTimeout(), $asyncOptions->noWait()),
          );
        },
    );
  }
}
