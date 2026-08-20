<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders normalized CLI errors as machine-readable diagnostics.
 *
 * The envelope schema is stable; only the serialization format follows the
 * selected response formatter (TOON by default, JSON with `--format=json`).
 */
final class ErrorEnvelope
{
  private readonly ConsoleWriter $writer;
  private readonly FormatterSelectorInterface $selector;

  /**
   * Creates an error-envelope renderer.
   *
   * @param OutputInterface|null            $output   Symfony output used for stderr.
   * @param FormatterSelectorInterface|null $selector Active formatter; defaults to TOON.
   */
  public function __construct(
      OutputInterface|null $output = null,
      FormatterSelectorInterface|null $selector = null,
  ) {
    $this->writer   = new ConsoleWriter($output);
    $this->selector = $selector ?? new MutableFormatterSelector(new ToonFormatter());
  }

  /** Renders a known CLI exception using the stable error envelope schema. */
  public function renderToStderr(CliException $e): void {
    $this->writeEnvelope(
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

  /** Renders an unexpected throwable using a generic server-error envelope. */
  public function renderGenericToStderr(string $message, string $correlationId): void {
    $this->writeEnvelope(
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

  /** @param array<string, mixed> $envelope */
  private function writeEnvelope(array $envelope): void {
    $this->writer->writeDiagnostic($this->selector->current()->format($envelope));
  }
}
