<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders warnings as machine-readable diagnostics on stderr.
 *
 * Stdout remains reserved for command payloads so pipeline consumers can
 * process successful results independently from warnings.
 */
final class WarningEnvelope
{
  private readonly ConsoleWriter $writer;
  private readonly FormatterSelectorInterface $selector;

  /**
   * Creates a warning-envelope renderer.
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

  /** Renders a warning as one formatted document on stderr. */
  public function renderToStderr(string $message): void {
    $this->writer->writeDiagnostic(
        $this->selector->current()->format(
            [
              'kind'    => 'warning',
              'message' => $message,
            ],
        ),
    );
  }
}
