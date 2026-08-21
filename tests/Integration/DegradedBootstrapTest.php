<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration;

use ContaAzulCli\ContaAzulApplication;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use ContaAzulCli\Tests\Integration\Support\MemoryConsoleOutput;
use ContaAzulCli\Tests\Support\DecodedPayloads;
use ContaAzulCli\Tests\Support\TemporaryDirectories;
use Symfony\Component\Console\Input\ArgvInput;

use function file_put_contents;
use function getenv;
use function json_decode;
use function putenv;

use const JSON_THROW_ON_ERROR;

/**
 * The CLI with no usable credentials at all.
 *
 * This is the state `ca config init` exists to get out of, so the config
 * family has to stay registered *and* runnable exactly here. The failure the
 * published PHARs shipped with was the opposite: bootstrap failed, nothing but
 * Symfony's built-ins registered, and there was no way to fix it from the CLI
 * itself.
 *
 * `CA_CLI_ENV_FILE` points at an empty file so the repository's own `.env`
 * cannot rescue the bootstrap and quietly invalidate every assertion here.
 *
 * Everything the shell owns — its error envelope and the always-available
 * commands' renderer alike — writes to the output handed to `run()`, so these
 * cases assert the rendered text rather than settling for exit codes.
 */
final class DegradedBootstrapTest extends CommandTestCase
{
  use DecodedPayloads;
  use TemporaryDirectories;

  private const array ENV_VARS = [
    'CA_BOOTSTRAP_REFRESH_TOKEN',
    'CA_CLIENT_ID',
    'CA_CLIENT_SECRET',
    'CA_CLI_ENV_FILE',
    'HOME',
  ];

  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private string $home    = '';
  private string $envFile = '';

  protected function setUp(): void {
    foreach (self::ENV_VARS as $variable) {
      $this->originalEnv[$variable] = getenv($variable);
      putenv($variable);
    }

    $this->home    = $this->makeTemporaryDirectory('ca-degraded-home');
    $this->envFile = $this->makeTemporaryDirectory('ca-degraded-env') . '/.env';
    file_put_contents($this->envFile, '');

    putenv('HOME=' . $this->home);
    putenv('CA_CLI_ENV_FILE=' . $this->envFile);
  }

  protected function tearDown(): void {
    $this->removeTemporaryDirectories();

    foreach ($this->originalEnv as $variable => $value) {
      putenv($value === false ? $variable : $variable . '=' . $value);
    }
  }

  /** Bootstrap really did fail: no business command survived. */
  public function testBusinessCommandsAreNotRegistered(): void {
    self::assertFalse((new ContaAzulApplication())->has('pessoa list'));
  }

  /** The config family is registered anyway, which is the point of the module. */
  public function testConfigCommandsStayRegistered(): void {
    $application = new ContaAzulApplication();

    foreach (['config path', 'config show', 'config init', 'config set'] as $name) {
      self::assertTrue($application->has($name), $name);
    }
  }

  /** They share the one selector, so `--format` reaches them like any other command. */
  public function testConfigCommandsCarryTheSharedResponseFormatOption(): void {
    $application = new ContaAzulApplication();

    foreach (['config path', 'config show', 'config init', 'config set'] as $name) {
      $definition = $application->get($name)->getDefinition();
      self::assertTrue($definition->hasOption('format'), $name);
      self::assertSame('toon', $definition->getOption('format')->getDefault(), $name);
    }
  }

  /**
   * The shared `--format` option must not crowd out a config command's own
   * `#[Option]` attributes — the getNativeDefinition trap that already cost
   * this project one regression.
   */
  public function testConfigInitKeepsItsOwnForceOption(): void {
    self::assertTrue(
        (new ContaAzulApplication())->get('config init')->getDefinition()->hasOption('force'),
    );
  }

  /**
   * Registered is not enough: `config path` is the diagnostic an operator
   * reaches for in this state, so it has to run and render here.
   */
  public function testConfigPathRunsAndRendersWithoutCredentials(): void {
    [$status, $output] = $this->runCli('config path');

    self::assertSame(0, $status);
    self::assertSame('', $output->stderr());

    $payload = self::decodeEnvelope($output->stdout());
    self::assertSame($this->envFile, $payload['arquivo']);
    self::assertSame(
        ['CA_CLI_ENV_FILE', 'raiz do projeto', 'diretório do usuário'],
        self::column($payload['candidatos'], 'origem'),
    );
    self::assertSame('usado', self::field(self::rows($payload['candidatos'])[0], 'status'));
  }

  /** `config init` is the way out of this state, so it has to work from inside it. */
  public function testConfigInitRunsWithoutCredentials(): void {
    [$status, $output] = $this->runCli('config init');
    $created           = $this->home . '/.config/conta-azul-cli/.env';

    self::assertSame(0, $status);
    self::assertSame('', $output->stderr());
    self::assertFileExists($created);
    self::assertSame($created, self::decodeEnvelope($output->stdout())['arquivo']);
  }

  /** One shared selector: `--format` really does change what they emit. */
  public function testConfigCommandsHonorTheFormatOption(): void {
    [$status, $output] = $this->runCli('config path', '--format=json');

    self::assertSame(0, $status);

    $payload = json_decode($output->stdout(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($payload);
    self::assertArrayHasKey('candidatos', $payload);
  }

  /** Discovery still lists the config commands, so the way out is findable. */
  public function testDiscoveryStillListsTheConfigCommands(): void {
    [$status, $output] = $this->runCli('list', '--format=json');

    self::assertSame(0, $status);
    self::assertStringContainsString('config init', $output->stdout());
    self::assertStringNotContainsString('pessoa list', $output->stdout());
  }

  /** Everything else still fails, with an error that names the way out. */
  public function testBusinessCommandsStillReportTheBootstrapFailure(): void {
    [$status, $output] = $this->runCli('pessoa list');

    self::assertSame(1, $status);
    self::assertSame('', $output->stdout());
    self::assertStringContainsString('CA_CLIENT_ID', $output->stderr());
    self::assertStringContainsString('ca config init', $output->stderr());
  }

  /** @return array{int, MemoryConsoleOutput} */
  private function runCli(string ...$arguments): array {
    $application = new ContaAzulApplication();
    $application->setAutoExit(false);
    $output = new MemoryConsoleOutput();
    $status = $application->run(new ArgvInput(['ca', ...$arguments]), $output);

    return [$status, $output];
  }
}
