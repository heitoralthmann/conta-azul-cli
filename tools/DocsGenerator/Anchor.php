<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function preg_replace;
use function strtolower;
use function trim;

/**
 * Builds the stable heading anchors the reference links to.
 *
 * Anchors are derived from command names rather than from rendered headings so
 * that a command keeps its deep link even when it moves into a section shared
 * with other commands.
 */
final class Anchor
{
  /** Reduces a command name to the id used in `{ #id }` and in `#fragment` links. */
  public function forCommand(string $command): string {
    $slug = strtolower(trim($command));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

    return trim($slug, '-');
  }
}
