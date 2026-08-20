<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\OrcamentoCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Wiring plus the one thing wiring cannot prove: that `orcamento list`
 * sends each filter under the name production honors. The generic classes'
 * own request/response/error behavior is already covered in
 * tests/Integration/Command/Support/, so this does not repeat that here.
 */
final class OrcamentoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryBudgetCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new OrcamentoCommandModule(
        $this->orcamentosClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(4, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['orcamento list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['orcamento create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['orcamento get']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['orcamento excluir-lote']);
  }

  /**
   * Locks the nine filter names `GET /v1/orcamentos` actually honors.
   *
   * The endpoint answers 200 and silently drops query parameters it does
   * not recognize, so a renamed filter would return all 158 budgets
   * instead of failing — the trap that hid the `produto list --codigo` and
   * `servico list --busca` bugs. Every name below was proven against
   * production on 2026-08-19 with a value matching a single record.
   *
   * Note the `data-alteracao-*` values: unlike the other two date pairs,
   * this one is rejected unless it is a full ISO 8601 date-time.
   */
  public function testListSendsEveryFilterUnderTheNameProductionHonors(): void {
    $response = new MockResponse('{"itens":[],"total_itens":0}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new OrcamentoCommandModule(
        $this->orcamentosClient([$response]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $this->runCommand($byName['orcamento list'], [
      '--campo-ordenado-ascendente'  => 'NUMERO',
      '--campo-ordenado-descendente' => 'DATA',
      '--data-alteracao-ate'         => '2024-11-30T23:59:59',
      '--data-alteracao-de'          => '2024-11-01T00:00:00',
      '--data-criacao-ate'           => '2024-11-30',
      '--data-criacao-de'            => '2024-11-01',
      '--data-fim'                   => '2024-11-30',
      '--data-inicio'                => '2024-11-01',
      '--termo-busca'                => 'ICONE',
    ]);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame('NUMERO', $query['campo_ordenado_ascendente'] ?? null);
    self::assertSame('DATA', $query['campo_ordenado_descendente'] ?? null);
    self::assertSame('2024-11-30T23:59:59', $query['data_alteracao_ate'] ?? null);
    self::assertSame('2024-11-01T00:00:00', $query['data_alteracao_de'] ?? null);
    self::assertSame('2024-11-30', $query['data_criacao_ate'] ?? null);
    self::assertSame('2024-11-01', $query['data_criacao_de'] ?? null);
    self::assertSame('2024-11-30', $query['data_fim'] ?? null);
    self::assertSame('2024-11-01', $query['data_inicio'] ?? null);
    self::assertSame('ICONE', $query['termo_busca'] ?? null);
  }
}
