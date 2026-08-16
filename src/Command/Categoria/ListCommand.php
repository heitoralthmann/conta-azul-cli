<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Categoria;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'categoria list', description: 'Lista categorias financeiras')]
final class ListCommand extends Command
{


    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        private readonly PaginationValidator $paginationValidator,
    ) {
        parent::__construct();
    }


    protected function configure(): void {
        PaginationOptions::configure($this);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $pagination = PaginationOptions::fromInput($input, $this->paginationValidator);

            $this->jsonRenderer->render($this->client->listCategorias($pagination->page(), $pagination->pageSize()));

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
