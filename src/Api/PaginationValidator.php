<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

use function implode;
use function in_array;

/** Validates page sizes accepted by paginated Conta Azul endpoints. */
final class PaginationValidator
{
  private const array VALID_SIZES = [10, 20, 50, 100, 200, 500, 1000];

  /**
   * Rejects a page size that is not supported by the API.
   *
   * @throws CliException When the requested size is not in the documented list.
   */
  public function validatePageSize(int $size): void
  {
    if (! in_array($size, self::VALID_SIZES, true)) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Tamanho de página inválido: ' . $size . '. Valores aceitos: ' . implode(', ', self::VALID_SIZES),
      );
    }
  }
}
