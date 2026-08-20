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

  /**
   * Placeholder credentials, so that generating docs never needs real ones.
   *
   * `ContaAzulApplication` keeps `list` and `help` working when bootstrap
   * fails, and registers nothing else — a deliberate choice, so that a
   * misconfigured install can still render an actionable error. The side
   * effect is that `list --format=json` answers `200`-shaped JSON with four
   * built-ins and exit code 0 whether the CLI has no commands or merely no
   * credentials.
   *
   * Passing throwaway values makes bootstrap succeed deterministically, which
   * also stops the generated reference from depending on whatever happens to
   * sit in the developer's `.env`. Symfony's `Dotenv::load()` does not
   * override variables already present, so these win. No request is ever made.
   */
  private const array STUB_ENVIRONMENT = [
    'CA_CLIENT_ID'     => 'docs-generator',
    'CA_CLIENT_SECRET' => 'docs-generator',
  ];

  /** Binds the loader to a project root. */
  public function __construct(private readonly string $root) {
  }

  /**
   * Returns every business command, keyed by name and sorted.
   *
   * @return array<string, array{description: string, arguments: array<string, mixed>, options: array<string, mixed>}>
   *
   * @throws RuntimeException When the CLI cannot be executed or registers nothing.
   */
  public function load(): array {
    $environment = '';

    foreach (self::STUB_ENVIRONMENT as $name => $value) {
      $environment .= $name . '=' . escapeshellarg($value) . ' ';
    }

    $command = sprintf(
        '%s%s %s list --format=json',
        $environment,
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

    if ($result === []) {
      throw new RuntimeException(
          "`bin/ca list --format=json` não devolveu nenhum comando de negócio.\n"
          . 'Isso quer dizer que o bootstrap do CLI falhou e só os built-ins do '
          . "Symfony foram registrados —\nnão que a referência esteja errada. "
          . 'Rode `bin/ca list` para ver o erro real.',
      );
    }

    ksort($result);

    return $result;
  }
}
