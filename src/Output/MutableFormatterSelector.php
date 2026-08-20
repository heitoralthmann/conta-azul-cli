<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

/** Invocation-scoped holder for the active response formatter. */
final class MutableFormatterSelector implements FormatterSelectorInterface
{
  /** Starts the selector on the given formatter, typically the registry default. */
  public function __construct(private ResponseFormatterInterface $current) {
  }

  /** Returns the formatter selected for this invocation. */
  public function current(): ResponseFormatterInterface {
    return $this->current;
  }

  /** Replaces the formatter used by subsequent writes. */
  public function select(ResponseFormatterInterface $formatter): void {
    $this->current = $formatter;
  }
}
