<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
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
        private readonly JsonRenderer $jsonRenderer,
        string $jsonDescription,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
        $this->jsonDescription = $jsonDescription;
        parent::__construct($name);
        $this->setDescription($description);
    }


    private string $jsonDescription;


    /** Declares the JSON payload option accepted by the operation. */
    protected function configure(): void {
        $this->addOption('json', NULL, InputOption::VALUE_REQUIRED, $this->jsonDescription);
    }


    /** Parses input, invokes the operation, and renders its result. */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function () use ($input): void {
            $this->jsonRenderer->render(($this->operation)(JsonPayload::object($input->getOption('json'))));
          }
        );
    }


}
