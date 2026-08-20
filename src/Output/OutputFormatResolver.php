<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Symfony\Component\Console\Input\InputInterface;

use function implode;
use function is_string;
use function str_starts_with;

/**
 * Maps `--format` to a registered formatter name.
 *
 * Resolution happens from raw argv so it works before Symfony binds the
 * command definition — the same moment {@see \ContaAzulCli\ContaAzulApplication}
 * handles `--debug`.
 */
final class OutputFormatResolver
{
  /** Creates a resolver against the formatters the CLI currently ships. */
  public function __construct(private readonly FormatterRegistry $registry) {
  }

  /**
   * Returns the formatter name selected by this invocation.
   *
   * @throws CliException When `--format` is missing a value or unknown.
   */
  public function resolve(InputInterface $input): string {
    $explicitFormat = $input->hasParameterOption(['--format'], true);

    if ($explicitFormat) {
      $format = $this->readFormat($input);
      if (! $this->registry->has($format)) {
        throw new CliException(
            ErrorKind::ClientError,
            false,
            'Formato de saída desconhecido: "' . $format . '". Use: '
                . implode(', ', $this->registry->names()) . '.',
        );
      }

      return $format;
    }

    return $this->registry->default()->name();
  }

  /**
   * Reads the `--format` value from unbound input.
   *
   * @throws CliException When `--format` was passed without a usable value.
   */
  private function readFormat(InputInterface $input): string {
    $value = $input->getParameterOption(['--format'], null, true);
    if (! is_string($value) || $value === '' || str_starts_with($value, '-')) {
      throw new CliException(ErrorKind::ClientError, false, 'A opção --format exige um valor.');
    }

    return $value;
  }
}
