<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/** Fetches one financial installment by identifier. */
#[AsCommand(name: 'parcela get', description: 'Obtém uma parcela pelo ID')]
final class GetCommand extends Command
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

  /** Declares the required installment identifier argument. */
  protected function configure(): void {
    $this->addArgument('id', InputArgument::REQUIRED, 'ID da parcela');
  }

  /** Fetches the installment and renders a normalized error on failure. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $id = $input->getArgument('id');
          $this->jsonRenderer->render($this->client->getParcela(is_string($id) ? $id : ''));
        },
    );
  }
}
