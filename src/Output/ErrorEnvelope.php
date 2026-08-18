<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use JsonException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders normalized CLI errors as machine-readable JSON diagnostics.
 */
final class ErrorEnvelope
{
  private readonly JsonConsoleOutput $output;

  /**
   * Creates an error-envelope renderer.
   *
   * @param OutputInterface|null $output Symfony output used for stderr.
   */
  public function __construct(OutputInterface|null $output = null) {
    $this->output = new JsonConsoleOutput($output);
  }

  /**
   * Renders a known CLI exception using the stable error envelope schema.
   *
   * @throws JsonException If the envelope cannot be encoded as JSON.
   */
  public function renderToStderr(CliException $e): void {
    $this->output->renderError(
        [
          'correlation_id' => $e->correlationId,
          'http_status'    => $e->httpStatus,
          'kind'           => $e->kind->value,
          'message'        => $e->getMessage(),
          'protocol_id'    => $e->protocolId,
          'retryable'      => $e->retryable,
        ],
    );
  }

  /**
   * Renders an unexpected throwable using a generic server-error envelope.
   *
   * @throws JsonException If the envelope cannot be encoded as JSON.
   */
  public function renderGenericToStderr(string $message, string $correlationId): void {
    $this->output->renderError(
        [
          'correlation_id' => $correlationId,
          'http_status'    => null,
          'kind'           => ErrorKind::ServerError->value,
          'message'        => $message,
          'protocol_id'    => null,
          'retryable'      => false,
        ],
    );
  }
}
