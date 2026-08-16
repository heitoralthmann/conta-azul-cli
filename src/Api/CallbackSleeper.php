<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Adapts a callback to the sleeper contract, useful for deterministic tests. */
final class CallbackSleeper implements SleeperInterface
{


    /**
     * @param \Closure(float):void $callback
     */
    public function __construct(private readonly \Closure $callback) {
    }


    /**
     * Delegates the delay to the callback supplied at construction time.
     */
    public function sleep(float $seconds): void {
        ($this->callback)($seconds);
    }


}
