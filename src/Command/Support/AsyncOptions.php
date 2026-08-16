<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/** Reads the common options used by commands that may wait for a protocol. */
final readonly class AsyncOptions
{
    private const DEFAULT_POLL_TIMEOUT = 60;


    /**
     * @param int $pollTimeout Maximum number of seconds to wait for completion
     * @param bool $noWait Whether the command should return before completion
     */
    public function __construct(
        private int $pollTimeout,
        private bool $noWait,
    ) {
    }


    /** Adds the shared asynchronous options to a command definition. */
    public static function configure(Command $command): void {
        $command
            ->addOption('poll-timeout', NULL, InputOption::VALUE_REQUIRED, 'Timeout de polling em segundos', (string) self::DEFAULT_POLL_TIMEOUT)
            ->addOption('no-wait', NULL, InputOption::VALUE_NONE, 'Retorna imediatamente sem aguardar confirmação assíncrona');
    }


    /** Creates options from the values supplied to a Symfony command. */
    public static function fromInput(InputInterface $input): self {
        $pollTimeoutRaw = $input->hasOption('poll-timeout') ? $input->getOption('poll-timeout') : NULL;
        $pollTimeout = is_numeric($pollTimeoutRaw) ? (int) $pollTimeoutRaw : self::DEFAULT_POLL_TIMEOUT;

        $noWait = $input->hasOption('no-wait') && (bool) $input->getOption('no-wait');

        return new self($pollTimeout, $noWait);
    }


    /** Returns the maximum number of seconds to wait for completion. */
    public function pollTimeout(): int {
        return $this->pollTimeout;
    }


    /** Returns whether the command should skip protocol polling. */
    public function noWait(): bool {
        return $this->noWait;
    }


}
