<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

use function array_filter;
use function array_values;
use function implode;
use function in_array;

/** Validates page sizes accepted by paginated Conta Azul endpoints. */
final class PaginationValidator
{
  /**
   * Largest page size any Conta Azul listing accepts.
   *
   * Most endpoints go this high. The ones that do not pass their own limit
   * to {@see self::validatePageSize()}.
   */
  public const int DEFAULT_MAX_SIZE = 1000;

  /**
   * Page size accepted by the endpoints that cap at one hundred.
   *
   * `GET /v1/servicos`, `GET /v1/notas-fiscais` and
   * `GET /v1/notas-fiscais-servico` answer `400` above this, with
   * "O tamanho da página deve ser um dos seguintes valores: 10, 20, 50 ou
   * 100". Measured against production on 2026-08-19.
   */
  public const int CAPPED_MAX_SIZE = 100;

  /**
   * Page size accepted by `GET /v1/captura/documentos/status`.
   *
   * Pairs with {@see PageSizeRule::AnySizeUpToMax}: this endpoint takes any
   * integer in `1..20`, not the discrete steps the others insist on.
   * Measured against production on 2026-08-19.
   */
  public const int CAPTURA_MAX_SIZE = 20;

  private const array VALID_SIZES = [10, 20, 50, 100, 200, 500, 1000];

  /**
   * Rejects a page size that is not supported by the target endpoint.
   *
   * `$maxSize` exists because the API disagrees with itself: three
   * listings stop at {@see self::CAPPED_MAX_SIZE} while the rest accept
   * {@see self::DEFAULT_MAX_SIZE}. Validating every endpoint against the
   * widest list let `--tamanho-pagina 200` reach the API and come back
   * `400`, which defeats the point of validating locally at all.
   *
   * `$rule` exists for the same reason one level down: the ceiling alone
   * does not describe `captura status`, which accepts every integer below
   * its own. Validating it against the discrete steps was wrong in both
   * directions — it let `1000` through to a `400` and rejected `15`, which
   * the endpoint answers `200`.
   *
   * @throws CliException When the requested size is not accepted by this endpoint.
   */
  public function validatePageSize(
      int $size,
      int $maxSize = self::DEFAULT_MAX_SIZE,
      PageSizeRule $rule = PageSizeRule::DiscreteSizes,
  ): void {
    if ($rule === PageSizeRule::AnySizeUpToMax) {
      if ($size < 1 || $size > $maxSize) {
        throw new CliException(
            ErrorKind::ClientError,
            false,
            'Tamanho de página inválido: ' . $size . '. Valores aceitos: 1 a ' . $maxSize,
        );
      }

      return;
    }

    $accepted = $this->acceptedSizes($maxSize);

    if (! in_array($size, $accepted, true)) {
      throw new CliException(
          ErrorKind::ClientError,
          false,
          'Tamanho de página inválido: ' . $size . '. Valores aceitos: ' . implode(', ', $accepted),
      );
    }
  }

  /**
   * Returns the sizes this endpoint accepts, in ascending order.
   *
   * @return list<int>
   */
  private function acceptedSizes(int $maxSize): array {
    return array_values(array_filter(self::VALID_SIZES, static fn (int $size): bool => $size <= $maxSize));
  }
}
