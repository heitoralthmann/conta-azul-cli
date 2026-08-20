<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use RuntimeException;

use function escapeshellarg;
use function in_array;
use function json_decode;
use function ksort;
use function shell_exec;
use function sprintf;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * Reads every command definition back out of the running CLI.
 *
 * Introspecting the built application — rather than parsing the command
 * classes — means the reference documents what the CLI actually accepts,
 * including anything Symfony derives from the `#[Argument]` and `#[Option]`
 * attributes.
 */
final class DefinitionLoader
{
  /** Symfony's own commands, which sit outside the CLI's output contract. */
  private const array BUILTIN_COMMANDS = ['completion', 'help', 'list'];

  /** Binds the loader to a project root. */
  public function __construct(private readonly string $root) {
  }

  /**
   * Returns every business command, keyed by name and sorted.
   *
   * @return array<string, array{description: string, arguments: array<string, mixed>, options: array<string, mixed>}>
   *
   * @throws RuntimeException When the CLI cannot be executed.
   */
  public function load(): array {
    $command = sprintf(
        '%s %s list --format=json',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($this->root . '/bin/ca'),
    );

    $output = shell_exec($command);

    if ($output === null || $output === false || $output === '') {
      throw new RuntimeException('Não foi possível executar `bin/ca list --format=json`.');
    }

    $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    $result  = [];

    foreach ($decoded['commands'] as $definition) {
      $name = $definition['name'];

      if (str_starts_with($name, '_') || in_array($name, self::BUILTIN_COMMANDS, true)) {
        continue;
      }

      $result[$name] = [
        'arguments'   => $definition['definition']['arguments'] ?: [],
        'description' => $definition['description'],
        'options'     => $definition['definition']['options'] ?: [],
      ];
    }

    ksort($result);

    return $result;
  }
}
