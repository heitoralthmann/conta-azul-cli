<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Config;

use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\ConfigKeys;
use ContaAzulCli\Config\EnvironmentSnapshot;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Dotenv\Dotenv;
use Throwable;

use function file_get_contents;
use function is_string;

/** Reports the effective configuration and where each value came from. */
#[AsCommand(
    name: 'config show',
    description: 'Mostra a configuração efetiva e a origem de cada valor',
    help: <<<'HELP'
    A coluna "origem" diz de onde cada valor veio: "ambiente" para uma variável
    exportada no shell, "arquivo" para uma definida no arquivo em vigor, e
    "default" para o valor compilado no CLI.

    A saída nunca contém segredos: CA_CLIENT_SECRET e CA_BOOTSTRAP_REFRESH_TOKEN
    aparecem apenas como "(definido)" ou "(ausente)", sem máscara parcial e sem
    opção para revelar. É o que torna esta saída segura de colar num issue.
    HELP,
)]
final class ShowCommand extends Command
{
  /** Placeholder for a credential the CLI has, whose value it will not print. */
  private const string PRESENT = '(definido)';

  /** Placeholder for a variable with no value and no default. */
  private const string ABSENT = '(ausente)';

  private readonly ConfigCommandExecutor $executor;

  /** Creates the command with the search path, the snapshot, and output collaborators. */
  public function __construct(
      private readonly ConfigFileLocator $locator,
      private readonly EnvironmentSnapshot $snapshot,
      private readonly Redactor $redactor,
      ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      ConfigCommandExecutor|null $executor = null,
  ) {
    $this->executor = $executor ?? new ConfigCommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Renders every known variable with its effective value and origin. */
  public function __invoke(): int {
    return $this->executor->execute(
        function (): void {
          $path       = $this->locator->locate()->path();
          $fileValues = $this->parseFile($path);
          $values     = [];

          foreach (ConfigKeys::names() as $name) {
            $configured = $this->configuredValue($name, $fileValues);

            $values[] = [
              'chave'  => $name,
              'origem' => $this->origin($name, $fileValues),
              'valor'  => $this->displayValue($name, $configured),
            ];
          }

          $this->responseRenderer->render(
              [
                'arquivo' => $path,
                'valores' => $values,
              ],
          );
        },
    );
  }

  /**
   * Parses the environment file that is currently in effect.
   *
   * The file is re-parsed here rather than read back through `getenv()`,
   * which by this point can no longer tell a value that came from the file
   * from one the operator exported — the whole point of the origin column.
   *
   * @return array<string, string>
   *
   * @throws ConfigException When the file exists but cannot be read or parsed.
   */
  private function parseFile(string|null $path): array {
    if ($path === null) {
      return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
      throw new ConfigException('Não foi possível ler ' . $path . '.');
    }

    try {
      $parsed = (new Dotenv())->parse($contents, $path);
    } catch (Throwable $e) {
      throw new ConfigException(
          'Não foi possível interpretar ' . $path . ': ' . $e->getMessage(),
          0,
          $e,
      );
    }

    $values = [];
    foreach ($parsed as $name => $value) {
      if (! is_string($name) || ! is_string($value)) {
        continue;
      }

      $values[$name] = $value;
    }

    return $values;
  }

  /**
   * Returns the value actually configured for a variable, ignoring defaults.
   *
   * Empty counts as absent throughout, matching the loader that falls back to
   * the compiled default for an empty variable.
   *
   * @param array<string, string> $fileValues
   */
  private function configuredValue(string $name, array $fileValues): string|null {
    $fromEnvironment = $this->snapshot->get($name);
    if ($fromEnvironment !== null) {
      return $fromEnvironment;
    }

    $fromFile = $fileValues[$name] ?? '';

    return $fromFile === '' ? null : $fromFile;
  }

  /**
   * Names where a variable's effective value came from.
   *
   * @param array<string, string> $fileValues
   */
  private function origin(string $name, array $fileValues): string {
    if ($this->snapshot->has($name)) {
      return 'ambiente';
    }

    return ($fileValues[$name] ?? '') !== '' ? 'arquivo' : 'default';
  }

  /**
   * Renders one value, never disclosing a credential.
   *
   * Credentials collapse to a presence flag with no partial masking: a prefix
   * is still material an attacker can use, and half a secret in a bug report
   * is a secret in a bug report.
   */
  private function displayValue(string $name, string|null $configured): string {
    if ($this->redactor->isSensitive($name)) {
      return $configured === null ? self::ABSENT : self::PRESENT;
    }

    return $configured ?? ConfigKeys::default($name) ?? self::ABSENT;
  }
}
