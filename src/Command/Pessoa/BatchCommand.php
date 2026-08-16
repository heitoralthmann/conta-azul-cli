<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\JsonPayload;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BatchCommand extends Command
{


    /** @param 'activate'|'deactivate'|'delete' $operation */
    public function __construct(
        private readonly PessoasClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        string $name,
        private readonly string $operation,
    ) {
        parent::__construct($name);
        $this->setDescription(
          match ($operation) {
            'activate' => 'Ativa pessoas em lote',
            'deactivate' => 'Inativa pessoas em lote',
            'delete' => 'Exclui pessoas em lote',
          }
        );
    }


    protected function configure(): void {
        $this->addOption('json', NULL, InputOption::VALUE_REQUIRED, 'Payload JSON com a lista de uuids');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {
            $payload = JsonPayload::object($input->getOption('json'));
            $result  = match ($this->operation) {
                'activate' => $this->client->activatePessoas($payload),
                'deactivate' => $this->client->deactivatePessoas($payload),
                'delete' => $this->client->deletePessoas($payload),
            };
            $this->jsonRenderer->render($result);

            return Command::SUCCESS;
        } catch (CliException $e) {
            $this->errorEnvelope->renderToStderr($e);

            return Command::FAILURE;
        }
    }


}
