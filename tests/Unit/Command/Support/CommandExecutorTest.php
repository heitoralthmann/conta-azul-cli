<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Command\Support;

use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Command\Command;

/** Verifies command operation execution and error translation. */
final class CommandExecutorTest extends TestCase
{


    /** Successful operations return zero and are invoked exactly once. */
    public function testReturnsSuccessWhenOperationCompletes(): void {
        $invocations = 0;
        $executor = new CommandExecutor(new ErrorEnvelope(new BufferedOutput()));

        $status = $executor->execute(
          static function () use (&$invocations): void {
            $invocations++;
          }
        );

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(1, $invocations);
    }


    /** Known CLI failures are rendered and return the failure status. */
    public function testRendersCliExceptionAndReturnsFailure(): void {
        $output = new BufferedOutput();
        $executor = new CommandExecutor(new ErrorEnvelope($output));

        $status = $executor->execute(
          static function (): void {
            throw new CliException(ErrorKind::ClientError, FALSE, 'Entrada inválida.');
          }
        );

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Entrada inválida.', $output->fetch());
    }


}
