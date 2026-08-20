<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Module\VendaCommandModule;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Command\Venda\ImprimirCommand;
use ContaAzulCli\Command\Venda\ItensCommand;
use ContaAzulCli\Command\Venda\ProximoNumeroCommand;
use ContaAzulCli\Command\Venda\VendedoresCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpClient\Response\MockResponse;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Wiring plus the one thing wiring cannot prove: that `venda list` sends
 * each filter under the name production honors. The generic Support
 * classes' own behavior is already covered in
 * tests/Integration/Command/Support/, and the custom Venda commands have
 * their own tests, so this does not repeat that here.
 */
final class VendaCommandModuleTest extends CommandTestCase
{
  public function testRegistersEverySaleCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new VendaCommandModule(
        $this->vendasClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(9, $byName);
    self::assertInstanceOf(ResourceListCommand::class, $byName['venda list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['venda create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['venda get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['venda update']);
    self::assertInstanceOf(ImprimirCommand::class, $byName['venda imprimir']);
    self::assertInstanceOf(ItensCommand::class, $byName['venda itens']);
    self::assertInstanceOf(VendedoresCommand::class, $byName['venda vendedores']);
    self::assertInstanceOf(ProximoNumeroCommand::class, $byName['venda proximo-numero']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['venda excluir-lote']);
  }

  /**
   * Locks the eight filter names `GET /v1/venda/busca` actually honors.
   *
   * The endpoint answers 200 and silently drops query parameters it does
   * not recognize, so a renamed filter would return the whole collection
   * instead of failing — the same trap that hid the `produto list --codigo`
   * and `servico list --busca` bugs. Every name below was proven against
   * production on 2026-08-19 with a value matching a single record.
   */
  public function testListSendsEveryFilterUnderTheNameProductionHonors(): void {
    $response = new MockResponse('{"itens":[],"total_itens":0}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new VendaCommandModule(
        $this->vendasClient([$response]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $this->runCommand($byName['venda list'], [
      '--campo-ordenado-ascendente'  => 'NUMERO',
      '--campo-ordenado-descendente' => 'DATA',
      '--data-criacao-ate'           => '2020-09-30',
      '--data-criacao-de'            => '2020-09-01',
      '--data-fim'                   => '2020-09-30',
      '--data-inicio'                => '2020-09-01',
      '--termo-busca'                => 'HOPE',
      '--totais'                     => 'CANCELED',
    ]);

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);

    self::assertSame('HOPE', $query['termo_busca'] ?? null);
    self::assertSame('2020-09-01', $query['data_inicio'] ?? null);
    self::assertSame('2020-09-30', $query['data_fim'] ?? null);
    self::assertSame('2020-09-01', $query['data_criacao_de'] ?? null);
    self::assertSame('2020-09-30', $query['data_criacao_ate'] ?? null);
    self::assertSame('DATA', $query['campo_ordenado_descendente'] ?? null);
    self::assertSame('NUMERO', $query['campo_ordenado_ascendente'] ?? null);
    self::assertSame('CANCELED', $query['totais'] ?? null);
  }

  /**
   * The counterpart to the cap on `servico list`: most listings really do
   * take 1000, and narrowing them would reject calls the API accepts.
   * `GET /v1/venda/busca` was measured at 200, 500 and 1000 against
   * production on 2026-08-19.
   */
  public function testListStillAcceptsThePageSizesTheEndpointSupports(): void {
    $response = new MockResponse('{"itens":[],"total_itens":0}', ['http_code' => 200]);
    $output   = $this->newOutput();
    $module   = new VendaCommandModule(
        $this->vendasClient([$response]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    $tester = $this->runCommand($byName['venda list'], ['--tamanho-pagina' => '1000']);

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());

    parse_str((string) parse_url($response->getRequestUrl(), PHP_URL_QUERY), $query);
    self::assertSame('1000', $query['tamanho_pagina'] ?? null);
  }
}
