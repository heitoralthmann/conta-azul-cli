<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function array_keys;
use function file_get_contents;
use function implode;
use function sprintf;
use function str_contains;

/**
 * Cross-checks the curated notes against the CLI they claim to describe.
 *
 * This is what keeps the reference honest. Prose is written by hand and the
 * command surface moves underneath it, so every curated command and parameter
 * is proven to still exist, every existing command is proven to be documented,
 * and every generated page is proven to be reachable from the site nav.
 * A violation fails the build rather than shipping documentation for something
 * that is no longer there.
 */
final class Validator
{
  /** Collects every disagreement between the fragments and the CLI. */
  public function __construct(private readonly string $root) {
  }

  /**
   * Returns a human-readable problem list; empty means the reference is sound.
   *
   * @param array<string, array<string, mixed>> $definitions
   * @param array<string, array<string, mixed>> $fragments
   *
   * @return list<string>
   */
  public function validate(array $definitions, array $fragments): array {
    $errors     = [];
    $documented = [];

    foreach ($fragments as $slug => $fragment) {
      foreach ($fragment['sections'] as $index => $section) {
        $where = sprintf('%s.yaml seção %d', $slug, $index);

        foreach ($section['commands'] as $command) {
          if (! isset($definitions[$command])) {
            $errors[] = sprintf('%s: documenta `%s`, que não existe no CLI.', $where, $command);
            continue;
          }

          if (isset($documented[$command])) {
            $errors[] = sprintf('%s: `%s` já documentado em %s.', $where, $command, $documented[$command]);
            continue;
          }

          $documented[$command] = $where;
        }

        foreach (array_keys($section['params'] ?? []) as $parameter) {
          if ($this->isKnownParameter($definitions, $section['commands'], (string) $parameter)) {
            continue;
          }

          $errors[] = sprintf(
              '%s: nota para o parâmetro `%s`, que nenhum de [%s] aceita.',
              $where,
              $parameter,
              implode(', ', $section['commands']),
          );
        }
      }
    }

    foreach (array_keys($definitions) as $command) {
      if (isset($documented[$command])) {
        continue;
      }

      $errors[] = sprintf('`%s` existe no CLI e não está documentado em nenhum fragmento.', $command);
    }

    return [...$errors, ...$this->validateNav($fragments)];
  }

  /**
   * Reports group pages missing from `nav`, which MkDocs would silently drop.
   *
   * @param array<string, array<string, mixed>> $fragments
   *
   * @return list<string>
   */
  private function validateNav(array $fragments): array {
    $config = (string) file_get_contents($this->root . '/mkdocs.yml');
    $errors = [];

    foreach (array_keys($fragments) as $slug) {
      if (str_contains($config, 'referencia/' . $slug . '.md')) {
        continue;
      }

      $errors[] = sprintf('`referencia/%s.md` não aparece no `nav` de mkdocs.yml.', $slug);
    }

    return $errors;
  }

  /**
   * Reports whether any of the given commands accepts a parameter by that name.
   *
   * @param array<string, array<string, mixed>> $definitions
   * @param list<string>                        $commands
   */
  private function isKnownParameter(array $definitions, array $commands, string $parameter): bool {
    foreach ($commands as $command) {
      $definition = $definitions[$command] ?? null;

      if ($definition === null) {
        continue;
      }

      if (isset($definition['arguments'][$parameter]) || isset($definition['options'][$parameter])) {
        return true;
      }
    }

    return false;
  }
}
