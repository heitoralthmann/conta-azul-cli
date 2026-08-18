<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Api\NotasFiscaisClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\NotaFiscal\GetCommand as NotaFiscalGetCommand;
use ContaAzulCli\Command\NotaFiscal\ListCommand as NotaFiscalListCommand;
use ContaAzulCli\Command\NotaFiscalServico\ListCommand as NotaFiscalServicoListCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Console\Command\Command;

/** Registers commands backed by the invoices (notas fiscais) API client. */
final class NotaFiscalCommandModule implements CommandModuleInterface
{
  /** Connects the invoices API client and shared command collaborators. */
  public function __construct(
      private readonly NotasFiscaisClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
      private readonly WarningEnvelope $warningEnvelope,
      private readonly PeriodoPadrao $periodoPadrao,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new NotaFiscalListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
      new NotaFiscalGetCommand($this->client, $this->errorEnvelope, $this->jsonRenderer),
      new ResourceJsonCommand(
          'nota-fiscal vincular-mdfe',
          'Vincula uma ou mais notas fiscais a um MDF-e',
          $this->client->vincularMdfe(...),
          $this->errorEnvelope,
          $this->jsonRenderer,
          'Payload JSON do vínculo (chaves_acesso, identificador, status)',
      ),
      new NotaFiscalServicoListCommand(
          $this->client,
          $this->errorEnvelope,
          $this->jsonRenderer,
          $this->paginationValidator,
          $this->warningEnvelope,
          $this->periodoPadrao,
      ),
    ];
  }
}
