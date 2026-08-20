<?php

declare(strict_types=1);

namespace ContaAzulCli;

use ContaAzulCli\Bootstrap\ApplicationFactory;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\FormatterRegistry;
use ContaAzulCli\Output\FormatterSelectorInterface;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\MutableFormatterSelector;
use ContaAzulCli\Output\OutputFormatResolver;
use ContaAzulCli\Output\ToonFormatter;
use LogicException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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
use function is_string;
use function str_starts_with;
use function trim;

/** Symfony Console shell for the Conta Azul CLI. */
final class ContaAzulApplication extends Application
{
  private Logger|null $logger            = null;
  private Throwable|null $bootstrapError = null;
  private FormatterRegistry $formatterRegistry;
  private FormatterSelectorInterface $formatterSelector;
  private OutputFormatResolver $outputFormatResolver;

  /** @var list<string> */
  private array $applicationCommandNames = [];

  private bool $renderSymfonyErrors = false;

  /** Builds the application and registers feature modules from the factory. */
  public function __construct() {
    parent::__construct('ca', self::version());

    $factory = new ApplicationFactory();
    try {
      $components              = $factory->build();
      $this->logger            = $components->logger();
      $this->formatterRegistry = $components->formatterRegistry();
      $this->formatterSelector = $components->formatterSelector();
      $commands                = $components->commands();
      foreach ($commands as $command) {
        $this->configureOutputFormat($command);
        $this->applicationCommandNames[] = (string) $command->getName();
        foreach ($command->getAliases() as $alias) {
          if (! is_string($alias)) {
            throw new LogicException('Command aliases must be strings.');
          }

          $this->applicationCommandNames[] = $alias;
        }
      }

      $this->addCommands($commands);
    } catch (Throwable $e) {
      // Keep discovery/help available when configuration or an adapter
      // fails during bootstrap; run() renders the actionable error.
      $this->logger            = $factory->logger();
      $this->bootstrapError    = $e;
      $this->formatterRegistry = FormatterRegistry::withDefaults();
      $this->formatterSelector = new MutableFormatterSelector($this->formatterRegistry->default());
    }

    $this->outputFormatResolver = new OutputFormatResolver($this->formatterRegistry);
  }

  /** Keeps Symfony diagnostics for built-ins; project commands render structured errors themselves. */
  protected function doRenderThrowable(Throwable $e, OutputInterface $output): void {
    if (! $this->renderSymfonyErrors) {
      return;
    }

    parent::doRenderThrowable($e, $output);
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

    $isApplicationCommand      = $this->isApplicationCommand($input);
    $this->renderSymfonyErrors = ! $isApplicationCommand;

    if ($isApplicationCommand) {
      try {
        $this->applyOutputFormat($input);
      } catch (CliException $e) {
        $this->errorEnvelope()->renderToStderr($e);

        return 1;
      }
    }

    if ($this->bootstrapError !== null && ! $this->isAlwaysAvailableCommand($input)) {
      $this->errorEnvelope()->renderToStderr(
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

  /** Whether the invocation targets a command provided by this application. */
  private function isApplicationCommand(InputInterface $input): bool {
    $name = $input->getFirstArgument();

    return $name !== null && in_array($name, $this->applicationCommandNames, true);
  }

  /** Selects the response formatter from `--format` for this invocation. */
  private function applyOutputFormat(InputInterface $input): void {
    $name = $this->outputFormatResolver->resolve($input);
    $this->formatterSelector->select($this->formatterRegistry->get($name));
  }

  /** Builds an error renderer that follows the currently selected format. */
  private function errorEnvelope(): ErrorEnvelope {
    return new ErrorEnvelope(null, $this->formatterSelector);
  }

  /** Adds the shared response-format option to one project command. */
  private function configureOutputFormat(Command $command): void {
    $command->addOption(
        'format',
        null,
        InputOption::VALUE_REQUIRED,
        'Formato da resposta: toon (padrão) ou json',
        ToonFormatter::NAME,
        $this->formatterRegistry->names(),
    );
  }

  /** Adds CLI-only global options that do not belong to individual commands. */
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
