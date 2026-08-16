<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class ErrorEnvelope
{


    public function renderToStderr(CliException $e): void {
        $envelope = [
            'kind'           => $e->kind->value,
            'retryable'      => $e->retryable,
            'http_status'    => $e->httpStatus,
            'protocol_id'    => $e->protocolId,
            'correlation_id' => $e->correlationId,
            'message'        => $e->getMessage(),
        ];
        fwrite(
          STDERR, json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
          )."\n"
        );
    }


    public function renderGenericToStderr(string $message, string $correlationId): void {
        $envelope = [
            'kind'           => ErrorKind::ServerError->value,
            'retryable'      => FALSE,
            'http_status'    => NULL,
            'protocol_id'    => NULL,
            'correlation_id' => $correlationId,
            'message'        => $message,
        ];
        fwrite(
          STDERR, json_encode(
            $envelope,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
          )."\n"
        );
    }


}
