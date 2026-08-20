<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

/**
 * Holds the formatter used for the current CLI invocation.
 *
 * Commands compose a selector instead of a concrete formatter so the
 * application shell can switch formats from `--format` without
 * rebuilding the command graph.
 */
interface FormatterSelectorInterface
{
  /** Returns the formatter selected for this invocation. */
  public function current(): ResponseFormatterInterface;

  /** Replaces the formatter used by subsequent writes. */
  public function select(ResponseFormatterInterface $formatter): void;
}
