<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Parcela;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/**
 * Registers payment for one financial installment.
 *
 * Atalho para `baixa create`: monta o payload mínimo de uma baixa a partir de
 * três opções, em vez de exigir o JSON inteiro.
 *
 * Até 2026-08-19 este comando mandava `{valor, data}` num `PATCH` da própria
 * parcela, na crença de que "não existe subrecurso /baixar". Existe: quem
 * quita é `POST /parcelas/{id}/baixa`. O `PATCH` só atualiza a parcela, e
 * respondia `200` sem registrar pagamento nenhum — os dois campos não estão
 * no schema dele e a API descarta campo desconhecido em silêncio.
 */
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

  /** Validates payment input, invokes the API, and renders its result. */
  public function __invoke(
      #[Argument(description: 'ID da parcela')]
      string $id,
      #[Option(description: 'Valor da baixa (ex: 100.50)')]
      string|null $valor = null,
      #[Option(description: 'Data da baixa no formato YYYY-MM-DD')]
      string|null $data = null,
      #[Option(name: 'conta-financeira', description: 'Uuid da conta financeira que recebe a baixa')]
      string|null $contaFinanceira = null,
  ): int {
    return $this->commandExecutor->execute(
        function () use ($id, $valor, $data, $contaFinanceira): void {
          $this->requireOption($valor, '--valor');
          $this->requireOption($data, '--data');
          $this->requireOption($contaFinanceira, '--conta-financeira');

          $payload = [
            // A escrita chama isto de `composicao_valor`; toda leitura
            // devolve o mesmo objeto como `valor_composicao`.
            'composicao_valor' => ['valor_bruto' => (float) $valor],
            'conta_financeira' => $contaFinanceira,
            'data_pagamento'   => $data,
          ];

          $this->jsonRenderer->render($this->client->createBaixa($id, $payload));
        },
    );
  }

  /** Rejects a missing or blank required option with a client error. */
  private function requireOption(string|null $value, string $option): void {
    if ($value !== null && $value !== '') {
      return;
    }

    throw new CliException(ErrorKind::ClientError, false, 'A opção ' . $option . ' é obrigatória.');
  }
}
