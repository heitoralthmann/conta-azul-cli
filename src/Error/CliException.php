<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

final class CliException extends \RuntimeException
{
    public function __construct(
        public readonly ErrorKind $kind,
        public readonly bool $retryable,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $protocolId = null,
        public readonly string $correlationId = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
