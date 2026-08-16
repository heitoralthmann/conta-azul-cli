<?php

declare(strict_types=1);

namespace ContaAzulCli\Sniffs\Functions;

use PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\ForbiddenFunctionsSniff;

/**
 * Discourage the use of debugging functions.
 *
 * They're fine locally, we just don't want them committed.
 */
class DebuggingFunctionsSniff extends ForbiddenFunctionsSniff
{
  /**
   * A list of forbidden functions with their alternatives.
   *
   * The value is null if no alternative exists, i.e., the function should
   * just not be used.
   *
   * @var array<string, string|null>
   */
  // Untyped: the parent Generic.PHP.ForbiddenFunctions sniff declares this
  // property without a native type, and PHP forbids a child class from
  // adding one where the parent has none.
  // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
  public $forbiddenFunctions = [
    'dump' => null,
    'error_log' => null,
    'phpinfo' => null,
    'print_r' => null,
    'var_dump' => null,
    'var_export' => null,
  ];

  /**
   * If true, an error will be thrown; otherwise a warning.
   *
   * @var bool
   */
  // Untyped for the same reason as $forbiddenFunctions above.
  // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
  public $error = false;
}
