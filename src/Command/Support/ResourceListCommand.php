<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Commande reutilizável para listagens paginadas com filtros da API. */
final class ResourceListCommand extends Command
{


    /**
     * @param callable(int, int, array<string, mixed>): array<mixed> $list
     * @param array<string, string> $filterOptions CLI option => query parameter
     */
    public function __construct(
        string $name,
        string $description,
        private readonly mixed $list,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        private readonly PaginationValidator $paginationValidator,
        private readonly array $filterOptions=[],
    ) {
        parent::__construct($name);
        $this->setDescription($description);
    }


    protected function configure(): void {
        PaginationOptions::configure($this);

        foreach ($this->filterOptions as $option => $queryName) {
            $this->addOption($option, NULL, InputOption::VALUE_REQUIRED, "Filtro {$queryName}");
        }
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $pagination = PaginationOptions::fromInput($input, $this->paginationValidator);

            $filters = [];
            foreach ($this->filterOptions as $option => $queryName) {
                $value = $input->getOption($option);
                if (is_string($value) && $value !== '') {
                    $filters[$queryName] = $value;
                }
            }

            $this->jsonRenderer->render(($this->list)($pagination->page(), $pagination->pageSize(), $filters));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
