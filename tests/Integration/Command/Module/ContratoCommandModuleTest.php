<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Contrato\ListCommand;
use ContaAzulCli\Command\Contrato\ProximoNumeroCommand;
use ContaAzulCli\Command\Module\ContratoCommandModule;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Wiring plus the one thing wiring cannot prove: that `contrato list` sends
 * each filter under the name production honors. The generic
 * `ResourceJsonCommand`'s own behavior is already covered in
 * tests/Integration/Command/Support/, so this does not repeat that here.
 */
final class ContratoCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryContractCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new ContratoCommandModule(
        $this->contratosClient([]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(6, $byName);
    self::assertInstanceOf(ListCommand::class, $byName['contrato list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['contrato create']);
    self::assertInstanceOf(ProximoNumeroCommand::class, $byName['contrato proximo-numero']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato get']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato delete']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['contrato encerrar']);
  }

  /**
   * Locks the six query parameters `GET /v1/contratos` actually honors.
   *
   * The endpoint answers 200 and silently drops query parameters it does
   * not recognize, so a renamed filter would return every contract instead
   * of failing — the trap that hid the `produto list --codigo` and
   * `servico list --busca` bugs. Each name was proven against production on
   * 2026-08-19 with a value matching a single contract.
   *
   * The date pair is not optional here, unlike every other listing: the API
   * rejects a call missing either side, which is why `ListCommand` fills in
   * the current month rather than leaving them out.
   */
  public function testListSendsEveryFilterUnderTheNameProductionHonors(): void {
    $response = new MockResponse('{"itens_totais":0,"itens":[]}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new ContratoCommandModule(
        $this->contratosClient([$response]),
        new ErrorEnvelope($output),
        new JsonRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $this->runCommand($byName['contrato list'], [
      '--busca-textual'              => 'HOPE',
      '--campo-ordenado-ascendente'  => 'DATA_INICIO',
      '--campo-ordenado-descendente' => 'DATA_FIM',
      '--cliente-id'                 => '66de1cb1-c8e4-422b-9e55-6f2cfa7968ac',
      '--data-fim'                   => '2030-12-31',
      '--data-inicio'                => '2015-01-01',
    ]);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame('HOPE', $query['busca_textual'] ?? null);
    self::assertSame('DATA_INICIO', $query['campo_ordenado_ascendente'] ?? null);
    self::assertSame('DATA_FIM', $query['campo_ordenado_descendente'] ?? null);
    self::assertSame('66de1cb1-c8e4-422b-9e55-6f2cfa7968ac', $query['cliente_id'] ?? null);
    self::assertSame('2030-12-31', $query['data_fim'] ?? null);
    self::assertSame('2015-01-01', $query['data_inicio'] ?? null);
  }
}
