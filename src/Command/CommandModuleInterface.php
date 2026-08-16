<?php

declare(strict_types=1);

namespace ContaAzulCli\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Provides the commands owned by one cohesive CLI feature.
 *
 * Keeping registration behind this interface prevents the application shell
 * from knowing how feature commands are constructed.
 */
interface CommandModuleInterface
{
  /**
   * Returns the commands that this feature contributes to the CLI.
   *
   * @return list<Command>
   */
  public function commands(): array;
}
