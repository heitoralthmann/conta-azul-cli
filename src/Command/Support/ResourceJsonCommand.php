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
    /** @param callable(array<string, mixed>): array<mixed> $operation */
    public function __construct(
        string $name,
        string $description,
        private readonly mixed $operation,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        string $jsonDescription,
    ) {
        $this->jsonDescription = $jsonDescription;
        parent::__construct($name);
        $this->setDescription($description);
    }

    private string $jsonDescription;

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_REQUIRED, $this->jsonDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->jsonRenderer->render(($this->operation)(JsonPayload::object($input->getOption('json'))));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
