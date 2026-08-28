<?php

declare(strict_types=1);

namespace ContaAzulCli;

use ContaAzulCli\Bootstrap\ApplicationFactory;
use ContaAzulCli\Command\Module\ConfigCommandModule;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Config\EnvironmentSnapshot;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\FormatterRegistry;
use ContaAzulCli\Output\FormatterSelectorInterface;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\MutableFormatterSelector;
use ContaAzulCli\Output\OutputFormatResolver;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
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
use function array_keys;
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
  private readonly FormatterRegistry $formatterRegistry;
  private readonly FormatterSelectorInterface $formatterSelector;
  private readonly OutputFormatResolver $outputFormatResolver;
  private readonly ErrorEnvelope $errorEnvelope;
  private readonly ResponseRenderer $responseRenderer;

  /** @var list<string> */
  private array $applicationCommandNames = [];

  /** @var list<string> */
  private array $alwaysAvailableCommandNames = [];

  private bool $renderSymfonyErrors = false;

  /** Builds the application and registers feature modules from the factory. */
  public function __construct() {
    // Captured before anything loads a .env file: once Dotenv has run with
    // usePutenv(true), nothing can tell a variable the operator exported from
    // one that came out of the file. `ca config show` needs that distinction.
    $environment = EnvironmentSnapshot::capture();

    parent::__construct('ca', self::version());

    // Read back rather than listed by hand: these are whatever Symfony
    // Console registers on its own, and they exist regardless of this
    // project's bootstrap. Taken before any project command is added.
    $symfonyCommands = array_keys($this->all());

    // Owned here rather than by the factory, because the always-available
    // commands are registered before the factory runs and every renderer in
    // the process has to share this one selector for `--format` to hold.
    $this->formatterRegistry    = FormatterRegistry::withDefaults();
    $this->formatterSelector    = new MutableFormatterSelector($this->formatterRegistry->default());
    $this->outputFormatResolver = new OutputFormatResolver($this->formatterRegistry);

    // One envelope and one renderer, shared by the shell and the
    // always-available commands. Both start on the real console and are
    // pointed at the invocation's output by run(), so everything this shell
    // owns writes to the same place.
    $this->errorEnvelope    = new ErrorEnvelope(null, $this->formatterSelector);
    $this->responseRenderer = new ResponseRenderer(null, $this->formatterSelector);

    // Registered outside the try: `ca config init` has to exist precisely in
    // the state where bootstrap fails, since it is what creates the
    // credentials whose absence made it fail.
    $alwaysAvailable                   = new ConfigCommandModule(
        ConfigFileLocator::forRuntime(),
        new EnvFileWriter(),
        new Redactor(),
        $environment,
        $this->errorEnvelope,
        $this->responseRenderer,
    );
    $this->alwaysAvailableCommandNames = [
      ...$symfonyCommands,
      ...$this->registerCommands($alwaysAvailable->commands()),
    ];

    $factory = new ApplicationFactory($this->formatterSelector);
    try {
      $components   = $factory->build();
      $this->logger = $components->logger();
      $this->registerCommands($components->commands());
    } catch (Throwable $e) {
      // Keep discovery/help and the config family available when
      // configuration or an adapter fails during bootstrap; run() renders the
      // actionable error for everything else.
      $this->logger         = $factory->logger();
      $this->bootstrapError = $e;
    }
  }

  /**
   * Registers commands and returns the names they answer to.
   *
   * @param list<Command> $commands
   *
   * @return list<string>
   */
  private function registerCommands(array $commands): array {
    $names = [];
    foreach ($commands as $command) {
      $this->configureOutputFormat($command);
      $names[] = (string) $command->getName();
      foreach ($command->getAliases() as $alias) {
        if (! is_string($alias)) {
          throw new LogicException('Command aliases must be strings.');
        }

        $names[] = $alias;
      }
    }

    $this->addCommands($commands);
    foreach ($names as $name) {
      $this->applicationCommandNames[] = $name;
    }

    return $names;
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
    // Symfony's contract is that a caller-supplied output receives everything
    // this run writes. Renderers are built during construction, before that
    // output exists, so they are pointed at it here — otherwise the shell and
    // its always-available commands would write past it to the real console,
    // which is both a contract violation and untestable.
    if ($output !== null) {
      $this->errorEnvelope->redirectTo($output);
      $this->responseRenderer->redirectTo($output);
    }

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
        $this->errorEnvelope->renderToStderr($e);

        return 1;
      }
    }

    if ($this->bootstrapError !== null && ! $this->isAlwaysAvailableCommand($input)) {
      $this->errorEnvelope->renderToStderr(
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
   * Discovery, help, and the config family remain available when bootstrap failed.
   *
   * The list is derived from the always-available module rather than written
   * out by hand, so adding a config command cannot silently leave it locked
   * behind the very failure it exists to fix.
   */
  private function isAlwaysAvailableCommand(InputInterface $input): bool {
    $name = $input->getFirstArgument();

    return $name === null
    || in_array($name, $this->alwaysAvailableCommandNames, true)
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

  /** Adds the shared response-format option to one project command. */
  private function configureOutputFormat(Command $command): void {
    // Forces invokable commands to register their #[Option]/#[Argument]
    // attributes first; Command::getNativeDefinition() only does so while
    // its definition is still empty, and addOption() below would otherwise
    // permanently block that from ever happening.
    $command->getDefinition();

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

  /** Reports the bare semver, without the "ca" prefix Symfony adds by default. */
  public function getLongVersion(): string {
    return $this->getVersion();
  }

  /** Reads the version from the single-source VERSION file next to the project root. */
  private static function version(): string {
    $path = __DIR__ . '/../VERSION';

    return is_file($path) ? trim((string) file_get_contents($path)) : 'unknown';
  }
}
