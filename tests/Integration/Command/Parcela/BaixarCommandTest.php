<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Parcela;

use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;

use function json_decode;

final class BaixarCommandTest extends CommandTestCase
{
  private const string PARCELA = 'parcela-1';

  private const array VALID_INPUT = [
    '--conta-financeira' => 'conta-1',
    '--data'             => '2026-08-16',
    '--valor'            => '100.50',
    'id'                 => self::PARCELA,
  ];

  public function testRegistersPaymentAndRendersOnlyThePayloadToStdout(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([$this->jsonResponse(['id' => 'baixa-1', 'versao' => 0])]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, self::VALID_INPUT);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertSame(['id' => 'baixa-1', 'versao' => 0], self::decodePayload($output->stdout()));
    self::assertSame('', $output->stderr());
  }

  /**
   * Trava o endpoint e o payload reais.
   *
   * Até 2026-08-19 o comando mandava `{valor, data}` num `PATCH` da própria
   * parcela. Esse endpoint existe, mas atualiza a parcela em vez de quitá-la:
   * respondia `200` sem registrar pagamento, porque nenhum dos dois campos
   * está no schema dele e a API descarta campo desconhecido em silêncio.
   */
  public function testSettlesThroughTheBaixaSubresourceWithTheWriteSideFieldNames(): void {
    $captured = null;
    $output   = $this->newOutput();
    $command  = new BaixarCommand(
        $this->financeiroClientRecording($captured),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $this->runCommand($command, self::VALID_INPUT);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/parcelas/' . self::PARCELA . '/baixa',
        $captured['url'],
    );
    self::assertSame(
        [
          // A escrita é `composicao_valor`; toda leitura devolve
          // `valor_composicao`.
          'composicao_valor' => ['valor_bruto' => 100.5],
          'conta_financeira' => 'conta-1',
          'data_pagamento'   => '2026-08-16',
        ],
        json_decode((string) $captured['body'], true),
    );
  }

  /** @return array<string, array{string}> */
  public static function requiredOptionProvider(): array {
    return [
      '--conta-financeira' => ['--conta-financeira'],
      '--data'             => ['--data'],
      '--valor'            => ['--valor'],
    ];
  }

  #[DataProvider('requiredOptionProvider')]
  public function testMissingRequiredOptionFailsBeforeAnyApiCall(string $option): void {
    $input = self::VALID_INPUT;
    unset($input[$option]);

    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, $input);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('client_error', $envelope['kind']);
  }

  public function testAmbiguousServerErrorOnWriteIsNotRetryable(): void {
    $output  = $this->newOutput();
    $command = new BaixarCommand(
        $this->financeiroClient([$this->errorResponse(500)]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
    );

    $tester = $this->runCommand($command, self::VALID_INPUT);

    self::assertSame(Command::FAILURE, $tester->getStatusCode());
    $envelope = self::decodeEnvelope($output->stderr());
    self::assertSame('ambiguous', $envelope['kind']);
    self::assertFalse($envelope['retryable']);
  }
}
