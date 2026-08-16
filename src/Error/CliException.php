<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

final class CliException extends \RuntimeException
{


    public function __construct(
        public readonly ErrorKind $kind,
        public readonly bool $retryable,
        string $message,
        public readonly ?int $httpStatus=NULL,
        public readonly ?string $protocolId=NULL,
        public readonly string $correlationId='',
        ?\Throwable $previous=NULL,
    ) {
        parent::__construct($message, 0, $previous);
    }


}
