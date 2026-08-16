<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

final class JsonPayload
{


    /** @return array<string, mixed> */
    public static function object(mixed $value): array {
        if (!is_string($value) || $value === '') {
            throw new CliException(ErrorKind::ClientError, FALSE, 'A opção --json é obrigatória.');
        }

        try {
            $decoded = json_decode($value, TRUE, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CliException(ErrorKind::ClientError, FALSE, 'JSON inválido: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new CliException(ErrorKind::ClientError, FALSE, 'JSON deve ser um objeto.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }


}
