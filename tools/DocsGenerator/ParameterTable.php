<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function array_filter;
use function implode;
use function in_array;
use function is_array;

/**
 * Renders the parameter table of a reference section.
 *
 * The rows come from the live Console definitions, so a renamed option or a
 * changed default shows up without anyone editing prose. Curated cells win
 * where they exist: the definitions know an option's declared default, but not
 * that omitting it makes the CLI substitute the current month.
 */
final class ParameterTable
{
  /** Options every command inherits; documented once in the guide, not per command. */
  public const array GLOBAL_OPTIONS = [
    'ansi',
    'debug',
    'format',
    'help',
    'no-ansi',
    'no-interaction',
    'quiet',
    'silent',
    'verbose',
    'version',
  ];

  /**
   * Renders the table, or an empty string when the section takes no parameters.
   *
   * @param array<string, mixed>                $section
   * @param array<string, array<string, mixed>> $definitions
   */
  public function render(array $section, array $definitions): string {
    $curated = $section['params'] ?? [];
    $rows    = [];
    $seen    = [];

    foreach ($section['commands'] as $command) {
      $definition = $definitions[$command] ?? null;

      if ($definition === null) {
        continue;
      }

      foreach ($definition['arguments'] as $name => $argument) {
        if (isset($seen[$name])) {
          continue;
        }

        $seen[$name] = true;
        $rows[]      = $this->argumentRow((string) $name, $argument, $curated[$name] ?? []);
      }

      foreach ($definition['options'] as $name => $option) {
        if (isset($seen[$name]) || in_array($name, self::GLOBAL_OPTIONS, true)) {
          continue;
        }

        $seen[$name] = true;
        $rows[]      = $this->optionRow((string) $name, $option, $curated[$name] ?? []);
      }
    }

    if ($rows === []) {
      return '';
    }

    $showDefaults = array_filter($rows, static fn (array $row): bool => $row['default'] !== '—') !== [];

    $out = $showDefaults
    ? "| Parâmetro | Obrig. | Padrão | Descrição |\n|---|---|---|---|\n"
    : "| Parâmetro | Obrig. | Descrição |\n|---|---|---|\n";

    foreach ($rows as $row) {
      $cells = $showDefaults
      ? [$row['label'], $row['required'], $row['default'], $row['description']]
      : [$row['label'], $row['required'], $row['description']];

      $out .= '| ' . implode(' | ', $cells) . " |\n";
    }

    return $out . "\n";
  }

  /**
   * Builds one row for a positional argument.
   *
   * @param array<string, mixed> $argument
   * @param array<string, mixed> $curated
   *
   * @return array{default: string, description: string, label: string, required: string}
   */
  private function argumentRow(string $name, array $argument, array $curated): array {
    return [
      'default'     => $curated['default'] ?? $this->formatDefault($argument['default'] ?? null),
      'description' => $curated['description'] ?? ($argument['description'] ?: '—'),
      'label'       => $curated['label'] ?? '`<' . $name . '>`',
      'required'    => $curated['required'] ?? ($argument['is_required'] ? '**sim**' : 'não'),
    ];
  }

  /**
   * Builds one row for an option.
   *
   * Symfony has no notion of a required option, so a `--json` that the command
   * rejects you for omitting is only "required" in the curated cell.
   *
   * @param array<string, mixed> $option
   * @param array<string, mixed> $curated
   *
   * @return array{default: string, description: string, label: string, required: string}
   */
  private function optionRow(string $name, array $option, array $curated): array {
    return [
      'default'     => $curated['default'] ?? $this->formatDefault($option['default'] ?? null),
      'description' => $curated['description'] ?? ($option['description'] ?: '—'),
      'label'       => $curated['label'] ?? '`--' . $name . '`',
      'required'    => $curated['required'] ?? 'não',
    ];
  }

  /** Renders a definition default as a table cell. */
  private function formatDefault(mixed $default): string {
    return match (true) {
      $default === null, $default === false, $default === [], $default === '' => '—',
      $default === true => '`true`',
      is_array($default) => '`' . implode(', ', $default) . '`',
      default => '`' . $default . '`',
    };
  }
}
