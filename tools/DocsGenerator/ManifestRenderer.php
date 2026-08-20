<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function file_get_contents;
use function in_array;
use function json_encode;
use function strcmp;
use function trim;
use function usort;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders `commands.json`, the machine-readable view of the CLI surface.
 *
 * The primary consumer of this project is an agent, not a person. Handing it
 * structured data removes the need to parse Markdown to find out which options
 * a command takes — and, unlike the prose, this file is pure projection of the
 * Console definitions plus the endpoint mapping.
 */
final class ManifestRenderer
{
  /** Binds the renderer to a project root, which holds the VERSION file. */
  public function __construct(private readonly string $root) {
  }

  /**
   * Renders the manifest as pretty-printed JSON.
   *
   * @param array<string, array<string, mixed>> $fragments
   * @param array<string, array<string, mixed>> $definitions
   */
  public function render(array $fragments, array $definitions): string {
    $commands = [];

    foreach ($fragments as $slug => $fragment) {
      foreach ($fragment['sections'] as $section) {
        foreach ($section['commands'] as $command) {
          $definition = $definitions[$command];

          $commands[] = [
            'arguments'   => $this->arguments($definition['arguments']),
            'description' => $definition['description'],
            'endpoint'    => $section['endpoints'][$command] ?? null,
            'group'       => $slug,
            'name'        => $command,
            'options'     => $this->options($definition['options']),
            'verified'    => $section['status'] !== 'unverified',
          ];
        }
      }
    }

    usort($commands, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

    $manifest = [
      'cli'            => 'ca',
      'commands'       => $commands,
      'generated_by'   => 'tools/generate-docs.php',
      'global_options' => ParameterTable::GLOBAL_OPTIONS,
      'output_default' => 'toon',
      'version'        => trim((string) file_get_contents($this->root . '/VERSION')),
    ];

    return json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) . "\n";
  }

  /**
   * Reduces argument definitions to the fields an agent needs.
   *
   * @param array<string, mixed> $arguments
   *
   * @return list<array<string, mixed>>
   */
  private function arguments(array $arguments): array {
    $result = [];

    foreach ($arguments as $name => $argument) {
      $result[] = [
        'array'       => $argument['is_array'],
        'description' => $argument['description'],
        'name'        => $name,
        'required'    => $argument['is_required'],
      ];
    }

    return $result;
  }

  /**
   * Reduces option definitions to the fields an agent needs, minus the globals.
   *
   * @param array<string, mixed> $options
   *
   * @return list<array<string, mixed>>
   */
  private function options(array $options): array {
    $result = [];

    foreach ($options as $name => $option) {
      if (in_array($name, ParameterTable::GLOBAL_OPTIONS, true)) {
        continue;
      }

      $result[] = [
        'default'     => $option['default'],
        'description' => $option['description'],
        'multiple'    => $option['is_multiple'],
        'name'        => '--' . $name,
        'takes_value' => $option['accept_value'],
      ];
    }

    return $result;
  }
}
