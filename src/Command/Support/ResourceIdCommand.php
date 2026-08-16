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


    /** @param callable(string): array<mixed> $operation */
    public function __construct(
        string $name,
        string $description,
        private readonly mixed $operation,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        string $argumentDescription,
    ) {
        $this->argumentDescription = $argumentDescription;
        parent::__construct($name);
        $this->setDescription($description);
    }


    private string $argumentDescription;


    protected function configure(): void {
        $this->addArgument('id', InputArgument::REQUIRED, $this->argumentDescription);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $id = $input->getArgument('id');
            $this->jsonRenderer->render(($this->operation)(is_string($id) ? $id : ''));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
