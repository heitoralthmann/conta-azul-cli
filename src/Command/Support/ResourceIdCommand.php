<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Commande reutilizável para buscar ou excluir um recurso por ID. */
final class ResourceIdCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;


  /**
   * Creates a command that invokes a resource operation with one identifier.
   *
   * @param callable(string): array<mixed> $operation
   */
  public function __construct(
        string $name,
        string $description,
        private readonly mixed $operation,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        string $argumentDescription,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
    $this->argumentDescription = $argumentDescription;
    parent::__construct($name);
    $this->setDescription($description);
  }


  private string $argumentDescription;


  /** Declares the required resource identifier argument. */
  protected function configure(): void {
    $this->addArgument('id', InputArgument::REQUIRED, $this->argumentDescription);
  }


  /** Executes the operation and renders success or a normalized CLI error. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
      function () use ($input): void {
        $id = $input->getArgument('id');
        $this->jsonRenderer->render(($this->operation)(is_string($id) ? $id : ''));
      }
    );
  }


}
