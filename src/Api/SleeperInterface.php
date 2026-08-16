<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Provides a replaceable delay for retries and asynchronous polling. */
interface SleeperInterface
{


    /**
     * Waits for the requested number of seconds.
     *
     * Implementations may add bounded jitter to avoid synchronized retries.
     */
    public function sleep(float $seconds): void;


}
