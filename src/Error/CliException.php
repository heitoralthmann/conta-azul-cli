<?php

declare(strict_types=1);

namespace ContaAzulCli\Error;

/** Represents a normalized, machine-readable failure exposed by the CLI. */
final class CliException extends \RuntimeException
{


    /**
     * Creates an error with retry, HTTP, protocol, and correlation metadata.
     *
     * @param ErrorKind $kind Stable envelope classification.
     * @param bool $retryable Whether callers may safely retry the operation.
     * @param string $message Operator-facing diagnostic message.
     * @param int|null $httpStatus HTTP status when the failure came from an API response.
     * @param string|null $protocolId Asynchronous protocol identifier, if available.
     * @param string $correlationId Identifier shared by related API requests.
     * @param \Throwable|null $previous Underlying exception, when one exists.
     */

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
