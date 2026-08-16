<?php

declare(strict_types=1);

namespace ContaAzulCli;

use ContaAzulCli\Bootstrap\ApplicationFactory;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\Logger;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function str_starts_with;
use function trim;

/** Symfony Console shell for the Conta Azul CLI. */
final class ContaAzulApplication extends Application
{
  private Logger|null $logger            = null;
  private Throwable|null $bootstrapError = null;

  /** Builds the application and registers feature modules from the factory. */
  public function __construct() {
    parent::__construct('ca', self::version());

    $factory = new ApplicationFactory();
    try {
      $components   = $factory->build();
      $this->logger = $components->logger();
      $this->addCommands($components->commands());
    } catch (Throwable $e) {
      // Keep discovery/help available when configuration or an adapter
      // fails during bootstrap; run() renders the actionable error.
      $this->logger         = $factory->logger();
      $this->bootstrapError = $e;
    }
  }

  /** Suppresses Symfony's default text exception rendering. */
  protected function doRenderThrowable(Throwable $e, OutputInterface $output): void {
    // Commands handle their own error output via ErrorEnvelope.
  }

  /** Runs the CLI while preserving structured bootstrap error behavior. */
  public function run(InputInterface|null $input = null, OutputInterface|null $output = null): int {
    if ($input === null) {
      $rawArgv = $_SERVER['argv'] ?? [];
      $argv    = array_values(array_filter(is_array($rawArgv) ? $rawArgv : [], 'is_string'));
      $input   = $this->buildInput($argv);
    }

    // Structured logging is opt-in and goes to a JSONL file, never stderr.
    if ($input->hasParameterOption(['--verbose', '-v', '-vv', '-vvv', '--debug'], true)) {
      $this->logger?->enable();
    }

    if ($this->bootstrapError !== null && ! $this->isAlwaysAvailableCommand($input)) {
      (new ErrorEnvelope())->renderToStderr(
          new CliException(
              ErrorKind::ClientError,
              false,
              'Falha ao inicializar o CLI: ' . $this->bootstrapError->getMessage(),
              null,
              null,
              Uuid::uuid4()->toString(),
              $this->bootstrapError,
          ),
      );

      return 1;
    }

    try {
      return parent::run($input, $output);
    } catch (Throwable) {
      return 1;
    }
  }

  /**
   * Discovery and help remain available when bootstrap failed.
   */
  private function isAlwaysAvailableCommand(InputInterface $input): bool {
    $name = $input->getFirstArgument();

    return $name === null
    || in_array($name, ['list', 'help', 'completion'], true)
    || $input->hasParameterOption(['--help', '-h', '--version', '-V'], true);
  }

  /** Adds the CLI-only debug option to Symfony's global definition. */
  protected function getDefaultInputDefinition(): InputDefinition {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(
        new InputOption(
            'debug',
            null,
            InputOption::VALUE_NONE,
            'Grava log estruturado em ~/.cache/conta-azul-cli/log.jsonl',
        ),
    );

    return $definition;
  }

  /** @param list<string> $argv */
  private function buildInput(array $argv): ArgvInput {
    // Merge two-word command names (e.g. "auth login") arriving as
    // separate argv tokens into one Symfony command token.
    if (
          isset($argv[1], $argv[2])
          && ! str_starts_with($argv[1], '-')
          && ! str_starts_with($argv[2], '-')
    ) {
      $compound = $argv[1] . ' ' . $argv[2];
      if ($this->has($compound)) {
        $argv = [$argv[0], $compound, ...array_slice($argv, 3)];
      }
    }

    return new ArgvInput($argv);
  }

  /** Reads the version from the single-source VERSION file next to the project root. */
  private static function version(): string {
    $path = __DIR__ . '/../VERSION';

    return is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
  }
}
