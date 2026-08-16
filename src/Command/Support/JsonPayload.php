<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

/** Parses the JSON object accepted by commands that write API resources. */
final class JsonPayload
{


    /**
     * Decodes a command option and rejects missing, malformed, or list JSON.
     *
     * @return array<string, mixed>
     *
     * @throws CliException when the option is missing or is not a JSON object
     */
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
