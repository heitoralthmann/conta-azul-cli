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
 * Maps `--raw` / `--format` to a registered formatter name.
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
   * @throws CliException When `--format` is missing a value, unknown, or contradicts `--raw`.
   */
  public function resolve(InputInterface $input): string {
    $raw            = $input->hasParameterOption(['--raw'], true);
    $explicitFormat = $input->hasParameterOption(['--format'], true);

    if ($explicitFormat) {
      $format = $this->readFormat($input);
      if ($raw && $format !== JsonFormatter::NAME) {
        throw new CliException(
            ErrorKind::ClientError,
            false,
            'A opção --raw seleciona JSON e não pode ser combinada com --format=' . $format . '.',
        );
      }

      if ($raw) {
        return JsonFormatter::NAME;
      }

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

    if ($raw) {
      return JsonFormatter::NAME;
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
