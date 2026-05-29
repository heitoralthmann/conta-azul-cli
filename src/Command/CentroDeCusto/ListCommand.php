<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\CentroDeCusto;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'centro-de-custo list', description: 'Lista centros de custo')]
final class ListCommand extends Command
{
    public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->jsonRenderer->render($this->client->listCentrosDeCusto());

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }
}
