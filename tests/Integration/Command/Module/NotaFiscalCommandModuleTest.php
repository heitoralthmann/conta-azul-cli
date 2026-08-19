<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\NotaFiscalCommandModule;
use ContaAzulCli\Command\NotaFiscal\GetCommand as NotaFiscalGetCommand;
use ContaAzulCli\Command\NotaFiscal\ListCommand as NotaFiscalListCommand;
use ContaAzulCli\Command\NotaFiscalServico\ListCommand as NotaFiscalServicoListCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Confirms each command name maps to the right class, and that both listings
 * put their filters on the wire under the names production answers to.
 *
 * The filter assertions live here rather than in `NotasFiscaisClientTest`
 * because the client forwards whatever key it is handed — only a test that
 * runs the command and inspects the outgoing URL can catch a wrong mapping.
 * The generic `ResourceJsonCommand`'s own behavior is already covered in
 * tests/Integration/Command/Support/, so this does not repeat that here.
 */
final class NotaFiscalCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryInvoiceCommandWithTheExpectedShape(): void {
    $byName = $this->moduleCommands([]);

    self::assertCount(4, $byName);
    self::assertInstanceOf(NotaFiscalListCommand::class, $byName['nota-fiscal list']);
    self::assertInstanceOf(NotaFiscalGetCommand::class, $byName['nota-fiscal get']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['nota-fiscal vincular-mdfe']);
    self::assertInstanceOf(NotaFiscalServicoListCommand::class, $byName['nota-fiscal-servico list']);
  }

  /**
   * Every `nota-fiscal list` filter has to reach the API under the exact name
   * production answers to.
   *
   * `GET /v1/notas-fiscais` answers 200 and silently discards query parameters
   * it does not recognize, so a misspelled filter returns the whole collection
   * instead of failing. These three names were each proven against production
   * on 2026-08-19 with a value matching a single record; `numero_nota` was the
   * only name that worked out of six tried.
   */
  public function testListSendsEveryFilterUnderTheNameTheApiAnswersTo(): void {
    $response = new MockResponse('{"itens":[],"paginacao":{}}', ['http_code' => 200]);
    $byName   = $this->moduleCommands([$response]);

    $this->runCommand($byName['nota-fiscal list'], [
      '--data-final'        => '2026-08-16',
      '--data-inicial'      => '2026-08-02',
      '--documento-tomador' => '23111508000162',
      '--id-venda'          => '09f63460-07ff-4851-b81d-b623e9fff9ef',
      '--numero-nota'       => '238',
    ]);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame('23111508000162', $query['documento_tomador'] ?? null);
    self::assertSame('238', $query['numero_nota'] ?? null);
    self::assertSame('09f63460-07ff-4851-b81d-b623e9fff9ef', $query['id_venda'] ?? null);
    self::assertSame('2026-08-02', $query['data_inicial'] ?? null);
    self::assertSame('2026-08-16', $query['data_final'] ?? null);
  }

  /**
   * `nota-fiscal list` must default to a 15-day window.
   *
   * The endpoint rejects a wider span with a 400, so the current-month default
   * the command shipped with made the bare `nota-fiscal list` fail every time.
   */
  public function testListDefaultsToAWindowTheEndpointAccepts(): void {
    $response = new MockResponse('{"itens":[],"paginacao":{}}', ['http_code' => 200]);
    $byName   = $this->moduleCommands([$response], new PeriodoPadrao(new DateTimeImmutable('2026-08-16')));

    $this->runCommand($byName['nota-fiscal list']);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame('2026-08-02', $query['data_inicial'] ?? null);
    self::assertSame('2026-08-16', $query['data_final'] ?? null);
    self::assertLessThanOrEqual(
        15,
        (new DateTimeImmutable((string) $query['data_inicial']))
            ->diff(new DateTimeImmutable((string) $query['data_final']))->days,
    );
  }

  /**
   * Every `nota-fiscal-servico list` filter, including the repeatable ones.
   *
   * This listing drops unknown parameters silently too, so all eleven names
   * were proven individually against production on 2026-08-19. The repeatable
   * options have to arrive as arrays (`ids[0]=…&ids[1]=…`), which is what the
   * API matched.
   */
  public function testServicoListSendsEveryFilterUnderTheNameTheApiAnswersTo(): void {
    $response = new MockResponse('{"itens":[],"paginacao":{}}', ['http_code' => 200]);
    $byName   = $this->moduleCommands([$response]);

    $this->runCommand($byName['nota-fiscal-servico list'], [
      '--data-competencia-ate' => '2025-09-23',
      '--data-competencia-de'  => '2025-09-08',
      '--id-cliente'           => ['dfd9059e-9009-4291-bc43-81620dd4b4f8'],
      '--ids'                  => ['50760e5c-544e-4231-b5c0-b32f4338dc0a'],
      '--numero-nfse-final'    => '521',
      '--numero-nfse-inicial'  => '519',
      '--numero-rps-final'     => '755',
      '--numero-rps-inicial'   => '754',
      '--numero-venda'         => '6071',
      '--status'               => ['CANCELADA'],
      '--tipo-negociacao'      => 'CONTRATO',
    ]);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame(['50760e5c-544e-4231-b5c0-b32f4338dc0a'], $query['ids'] ?? null);
    self::assertSame(['dfd9059e-9009-4291-bc43-81620dd4b4f8'], $query['id_cliente'] ?? null);
    self::assertSame(['CANCELADA'], $query['status'] ?? null);
    self::assertSame('6071', $query['numero_venda'] ?? null);
    self::assertSame('519', $query['numero_nfse_inicial'] ?? null);
    self::assertSame('521', $query['numero_nfse_final'] ?? null);
    self::assertSame('754', $query['numero_rps_inicial'] ?? null);
    self::assertSame('755', $query['numero_rps_final'] ?? null);
    self::assertSame('CONTRATO', $query['tipo_negociacao'] ?? null);
    self::assertSame('2025-09-08', $query['data_competencia_de'] ?? null);
    self::assertSame('2025-09-23', $query['data_competencia_ate'] ?? null);
  }

  /**
   * Builds the module and returns its commands keyed by name.
   *
   * @param list<MockResponse> $responses
   *
   * @return array<string, Command>
   */
  private function moduleCommands(array $responses, PeriodoPadrao|null $periodoPadrao = null): array {
    $output = $this->newOutput();
    $module = new NotaFiscalCommandModule(
        $this->notasFiscaisClient($responses),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        $periodoPadrao ?? new PeriodoPadrao(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    return $byName;
  }
}
