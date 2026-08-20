<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Module;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Command\Categoria\ConfiguracaoPadraoCommand as CategoriaConfiguracaoPadraoCommand;
use ContaAzulCli\Command\Categoria\DreCommand as CategoriaDreCommand;
use ContaAzulCli\Command\Categoria\ListCommand as CategoriaListCommand;
use ContaAzulCli\Command\CentroDeCusto\ListCommand as CentroDeCustoListCommand;
use ContaAzulCli\Command\ContaAPagar\CreateCommand as ContaAPagarCreateCommand;
use ContaAzulCli\Command\ContaAPagar\ListCommand as ContaAPagarListCommand;
use ContaAzulCli\Command\ContaAReceber\CreateCommand as ContaAReceberCreateCommand;
use ContaAzulCli\Command\ContaAReceber\ListCommand as ContaAReceberListCommand;
use ContaAzulCli\Command\ContaFinanceira\ListCommand as ContaFinanceiraListCommand;
use ContaAzulCli\Command\ContaFinanceira\SaldoCommand as ContaFinanceiraSaldoCommand;
use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Financeiro\SaldoInicialCommand;
use ContaAzulCli\Command\Module\FinanceiroCommandModule;
use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Command\Parcela\GetCommand as ParcelaGetCommand;
use ContaAzulCli\Command\Parcela\ListCommand as ParcelaListCommand;
use ContaAzulCli\Command\Protocolo\GetCommand as ProtocoloGetCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Transferencia\ListCommand as TransferenciaListCommand;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;

/**
 * Wiring-only: confirms each command name maps to the right class. The
 * generic Support classes' own behavior is already covered in
 * tests/Integration/Command/Support/, and the custom Financeiro commands
 * have their own tests, so this does not repeat that here.
 */
final class FinanceiroCommandModuleTest extends CommandTestCase
{
  public function testRegistersEveryFinancialCommandWithTheExpectedShape(): void {
    $output = $this->newOutput();
    $module = new FinanceiroCommandModule(
        $this->financeiroClient([]),
        new ErrorEnvelope($output),
        new ResponseRenderer($output),
        new PaginationValidator(),
        new WarningEnvelope($output),
        new PeriodoPadrao(),
    );

    $byName = [];
    foreach ($module->commands() as $command) {
      $byName[(string) $command->getName()] = $command;
    }

    self::assertCount(27, $byName);
    self::assertInstanceOf(ContaAReceberListCommand::class, $byName['conta-a-receber list']);
    self::assertInstanceOf(ContaAReceberCreateCommand::class, $byName['conta-a-receber create']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['cobranca create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['cobranca get']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['cobranca delete']);
    self::assertInstanceOf(ContaAPagarListCommand::class, $byName['conta-a-pagar list']);
    self::assertInstanceOf(ContaAPagarCreateCommand::class, $byName['conta-a-pagar create']);
    self::assertInstanceOf(ParcelaGetCommand::class, $byName['parcela get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['parcela update']);
    self::assertInstanceOf(BaixarCommand::class, $byName['parcela baixar']);
    self::assertInstanceOf(ParcelaListCommand::class, $byName['parcela list']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['baixa create']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['baixa list']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['baixa get']);
    self::assertInstanceOf(ResourceIdJsonCommand::class, $byName['baixa update']);
    self::assertInstanceOf(ResourceIdCommand::class, $byName['baixa delete']);
    self::assertInstanceOf(ContaFinanceiraListCommand::class, $byName['conta-financeira list']);
    self::assertInstanceOf(ContaFinanceiraSaldoCommand::class, $byName['conta-financeira saldo']);
    self::assertInstanceOf(TransferenciaListCommand::class, $byName['transferencia list']);
    self::assertInstanceOf(CategoriaListCommand::class, $byName['categoria list']);
    self::assertInstanceOf(CategoriaConfiguracaoPadraoCommand::class, $byName['categoria configuracao-padrao']);
    self::assertInstanceOf(CategoriaDreCommand::class, $byName['categoria dre']);
    self::assertInstanceOf(CentroDeCustoListCommand::class, $byName['centro-de-custo list']);
    self::assertInstanceOf(ResourceJsonCommand::class, $byName['centro-de-custo create']);
    self::assertInstanceOf(AlteracoesCommand::class, $byName['financeiro alteracoes']);
    self::assertInstanceOf(SaldoInicialCommand::class, $byName['financeiro saldo-inicial']);
    self::assertInstanceOf(ProtocoloGetCommand::class, $byName['protocolo get']);
  }
}
