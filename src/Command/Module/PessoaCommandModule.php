<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Pessoa\BatchCommand as PessoaBatchCommand;
use ContaAzulCli\Command\Pessoa\BatchOperation;
use ContaAzulCli\Command\Pessoa\ContaConectadaCommand as PessoaContaConectadaCommand;
use ContaAzulCli\Command\Pessoa\CreateCommand as PessoaCreateCommand;
use ContaAzulCli\Command\Pessoa\GetCommand as PessoaGetCommand;
use ContaAzulCli\Command\Pessoa\LegadoCommand as PessoaLegadoCommand;
use ContaAzulCli\Command\Pessoa\ListCommand as PessoaListCommand;
use ContaAzulCli\Command\Pessoa\PatchCommand as PessoaPatchCommand;
use ContaAzulCli\Command\Pessoa\UpdateCommand as PessoaUpdateCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Command\Command;

/** Registers commands backed by the people API client. */
final class PessoaCommandModule implements CommandModuleInterface
{
  /** Connects the people API client and shared command collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new PessoaListCommand($this->client, $this->errorEnvelope, $this->responseRenderer, $this->paginationValidator),
      new PessoaCreateCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new PessoaGetCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new PessoaUpdateCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new PessoaPatchCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new PessoaLegadoCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
      new PessoaBatchCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          'pessoa ativar',
          BatchOperation::Activate,
      ),
      new PessoaBatchCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          'pessoa inativar',
          BatchOperation::Deactivate,
      ),
      new PessoaBatchCommand(
          $this->client,
          $this->errorEnvelope,
          $this->responseRenderer,
          'pessoa excluir',
          BatchOperation::Delete,
      ),
      new PessoaContaConectadaCommand($this->client, $this->errorEnvelope, $this->responseRenderer),
    ];
  }
}
