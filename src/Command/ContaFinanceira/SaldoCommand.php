<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\ContaFinanceira;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'conta-financeira saldo', description: 'Obtém o saldo de uma conta financeira')]
/** Fetches the current balance for one financial account. */
final class SaldoCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;


  /** Creates the command and its API/output collaborators. */
  public function __construct(
        private readonly FinanceiroClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
    parent::__construct();
  }


  /** Declares the required account identifier option. */
  protected function configure(): void {
    $this->addOption('id', NULL, InputOption::VALUE_REQUIRED, 'ID da conta financeira');
  }


  /** Validates the identifier, fetches the balance, and renders the result. */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->commandExecutor->execute(
      function () use ($input): void {
        $id = $input->getOption('id');
        if (!is_string($id) || $id === '') {
            throw new CliException(ErrorKind::ClientError, FALSE, 'A opção --id é obrigatória.');
        }
        $this->jsonRenderer->render($this->client->getSaldoContaFinanceira($id));
      }
    );
  }


}
