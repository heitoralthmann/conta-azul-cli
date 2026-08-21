<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Module;

use ContaAzulCli\Command\CommandModuleInterface;
use ContaAzulCli\Command\Config\InitCommand;
use ContaAzulCli\Command\Config\PathCommand;
use ContaAzulCli\Command\Config\SetCommand;
use ContaAzulCli\Command\Config\ShowCommand;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\EnvFileWriter;
use ContaAzulCli\Config\EnvironmentSnapshot;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Command\Command;

/**
 * Registers the configuration commands and owns their construction.
 *
 * Unlike every other module, this one is built before the application
 * bootstraps and stays registered when bootstrapping fails. That is the whole
 * point: `ca config init` has to exist precisely when there are no credentials
 * yet, which is the state where nothing else can be constructed.
 */
final class ConfigCommandModule implements CommandModuleInterface
{
  /** Connects the search path, the file writer, and the output collaborators. */
  public function __construct(
      private readonly ConfigFileLocator $locator,
      private readonly EnvFileWriter $writer,
      private readonly Redactor $redactor,
      private readonly EnvironmentSnapshot $snapshot,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
  ) {
  }

  /** @return list<Command> */
  public function commands(): array {
    return [
      new PathCommand($this->locator, $this->errorEnvelope, $this->responseRenderer),
      new ShowCommand(
          $this->locator,
          $this->snapshot,
          $this->redactor,
          $this->errorEnvelope,
          $this->responseRenderer,
      ),
      new InitCommand($this->locator, $this->writer, $this->errorEnvelope, $this->responseRenderer),
      new SetCommand($this->locator, $this->writer, $this->errorEnvelope, $this->responseRenderer),
    ];
  }
}
