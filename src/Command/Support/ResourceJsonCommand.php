<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Commande reutilizável para operações que recebem um objeto JSON. */
final class ResourceJsonCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /**
   * Creates a command that passes one JSON object to an operation.
   *
   * @param callable(array<string, mixed>): array<mixed> $operation
   */
  public function __construct(
      string $name,
      string $description,
      private readonly mixed $operation,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private string $jsonDescription,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct($name);

    $this->setDescription($description);
  }

  /** Declares the JSON payload option accepted by the operation. */
  protected function configure(): void {
    $this->addOption('json', null, InputOption::VALUE_REQUIRED, $this->jsonDescription);
  }

  /** Parses input, invokes the operation, and renders its result. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $this->responseRenderer->render(($this->operation)(JsonPayload::object($input->getOption('json'))));
        },
    );
  }
}
