<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Commande reutilizável para atualizar parcialmente um recurso por ID. */
final class ResourceIdJsonCommand extends Command
{
    private readonly CommandExecutor $commandExecutor;


    /**
     * Creates a command that passes an identifier and JSON object to an operation.
     *
     * @param callable(string, array<string, mixed>): array<mixed> $operation
     */
    public function __construct(
        string $name,
        string $description,
        private readonly mixed $operation,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        string $argumentDescription,
        string $jsonDescription,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
        $this->argumentDescription = $argumentDescription;
        $this->jsonDescription = $jsonDescription;
        parent::__construct($name);
        $this->setDescription($description);
    }


    private string $argumentDescription;
    private string $jsonDescription;


    /** Declares the identifier argument and JSON payload option. */
    protected function configure(): void {
        $this
            ->addArgument('id', InputArgument::REQUIRED, $this->argumentDescription)
            ->addOption('json', NULL, InputOption::VALUE_REQUIRED, $this->jsonDescription);
    }


    /** Parses input, invokes the operation, and renders its result. */
    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function () use ($input): void {
            $id = $input->getArgument('id');
            $this->jsonRenderer->render(
              ($this->operation)(
                is_string($id) ? $id : '',
                JsonPayload::object($input->getOption('json')),
              )
            );
          }
        );
    }


}
