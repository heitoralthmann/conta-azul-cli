<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class PaginationValidator
{
    private const VALID_SIZES = [10, 20, 50, 100, 200, 500, 1000];


    public function validatePageSize(int $size): void {
        if (!in_array($size, self::VALID_SIZES, TRUE)) {
            throw new CliException(
              ErrorKind::ClientError,
              FALSE,
              'Tamanho de página inválido: '.$size.'. Valores aceitos: '.implode(', ', self::VALID_SIZES),
            );
        }
    }


}
