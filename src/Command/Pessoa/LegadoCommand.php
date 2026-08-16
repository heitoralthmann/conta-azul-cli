<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/** Fetches a person through its legacy identifier. */
#[AsCommand(name: 'pessoa legado', description: 'Busca uma pessoa por ID legado')]
final class LegadoCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Declares the required legacy identifier argument. */
  protected function configure(): void
  {
    $this->addArgument('id', InputArgument::REQUIRED, 'ID legado da pessoa');
  }

  /** Fetches the legacy person and renders output or an error. */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $id = $input->getArgument('id');
          $this->jsonRenderer->render($this->client->getPessoaLegado(is_string($id) ? $id : ''));
        },
    );
  }
}
