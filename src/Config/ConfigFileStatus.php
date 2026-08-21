<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

/**
 * Why one entry of the environment-file search path was or was not used.
 *
 * The values are operator-facing: `ca config path` prints them verbatim, so
 * they have to explain the decision without further context.
 */
enum ConfigFileStatus: string
{
  case Used          = 'usado';
  case Missing       = 'não existe';
  case Unset         = 'não definido';
  case SkippedInPhar = 'pulado: dentro do PHAR';
  case SkippedNoHome = 'pulado: diretório home não resolvido';
  case NotReached    = 'ignorado: um candidato anterior venceu';
}
