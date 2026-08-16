<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\AsyncOptions;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/** Registers payment for one financial installment. */
#[AsCommand(name: 'parcela baixar', description: 'Registra a baixa (pagamento) de uma parcela')]
final class BaixarCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly FinanceiroClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Declares payment fields and asynchronous completion options. */
  protected function configure(): void
  {
    $this
          ->addArgument('id', InputArgument::REQUIRED, 'ID da parcela')
          ->addOption('valor', null, InputOption::VALUE_REQUIRED, 'Valor da baixa (ex: 100.50)')
          ->addOption('data', null, InputOption::VALUE_REQUIRED, 'Data da baixa no formato YYYY-MM-DD');
    AsyncOptions::configure($this);
  }

  /** Validates payment input, invokes the API, and renders its result. */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    return $this->commandExecutor->execute(
        function () use ($input): void {
          $rawId = $input->getArgument('id');
          $id    = is_string($rawId) ? $rawId : '';

          $valorOption = $input->getOption('valor');
          $dataOption  = $input->getOption('data');

          if (! is_string($valorOption) || $valorOption === '') {
              throw new CliException(
                  ErrorKind::ClientError,
                  false,
                  'A opção --valor é obrigatória.',
              );
          }

          if (! is_string($dataOption) || $dataOption === '') {
              throw new CliException(
                  ErrorKind::ClientError,
                  false,
                  'A opção --data é obrigatória.',
              );
          }

          $payload = [
            'valor' => (float) $valorOption,
            'data'  => $dataOption,
          ];

          $asyncOptions = AsyncOptions::fromInput($input);

          $this->jsonRenderer->render(
              $this->client->baixarParcela($id, $payload, $asyncOptions->pollTimeout(), $asyncOptions->noWait()),
          );
        },
    );
  }
}
