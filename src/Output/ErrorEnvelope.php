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
          'kind'           => $e->kind->value,
          'retryable'      => $e->retryable,
          'http_status'    => $e->httpStatus,
          'protocol_id'    => $e->protocolId,
          'correlation_id' => $e->correlationId,
          'message'        => $e->getMessage(),
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
          'kind'           => ErrorKind::ServerError->value,
          'retryable'      => false,
          'http_status'    => null,
          'protocol_id'    => null,
          'correlation_id' => $correlationId,
          'message'        => $message,
        ],
    );
  }
}
