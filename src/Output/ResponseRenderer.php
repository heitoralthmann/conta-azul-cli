<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Renders a successful command payload through the selected formatter.
 *
 * Commands stay format-agnostic: they pass structured data here and never
 * choose TOON or JSON themselves.
 */
final class ResponseRenderer
{
  private readonly ConsoleWriter $writer;
  private readonly FormatterSelectorInterface $selector;

  /**
   * Creates a success renderer.
   *
   * @param OutputInterface|null            $output   Symfony output used for stdout.
   * @param FormatterSelectorInterface|null $selector Active formatter; defaults to TOON.
   */
  public function __construct(
      OutputInterface|null $output = null,
      FormatterSelectorInterface|null $selector = null,
  ) {
    $this->writer   = new ConsoleWriter($output);
    $this->selector = $selector ?? new MutableFormatterSelector(new ToonFormatter());
  }

  /** Points subsequent writes at the output supplied for this invocation. */
  public function redirectTo(OutputInterface $output): void {
    $this->writer->redirectTo($output);
  }

  /** Renders a successful command result in the currently selected format. */
  public function render(mixed $data): void {
    $this->writer->writeSuccess($this->selector->current()->format($data));
  }
}
