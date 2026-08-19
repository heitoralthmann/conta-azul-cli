<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Support;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use JsonException;

use function array_is_list;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/** Parses the JSON object accepted by commands that write API resources. */
final class JsonPayload
{
  /**
   * Decodes a command option and rejects missing, malformed, or list JSON.
   *
   * @return array<string, mixed>
   *
   * @throws CliException when the option is missing or is not a JSON object.
   */
  public static function object(mixed $value): array {
    if (! is_string($value) || $value === '') {
      throw new CliException(ErrorKind::ClientError, false, 'A opção --json é obrigatória.');
    }

    try {
      $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      throw new CliException(ErrorKind::ClientError, false, 'JSON inválido: ' . $e->getMessage(), previous: $e);
    }

    // `json_decode('{}', true)` devolve `[]`, que `array_is_list()` considera
    // uma lista — então o objeto vazio era recusado com "JSON deve ser um
    // objeto", justamente o que ele é. Um `{}` legítimo tem que chegar na
    // API e receber de lá a resposta sobre quais campos faltam.
    if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
      throw new CliException(ErrorKind::ClientError, false, 'JSON deve ser um objeto.');
    }

    if ($decoded === [] && ! str_starts_with(ltrim($value), '{')) {
      throw new CliException(ErrorKind::ClientError, false, 'JSON deve ser um objeto.');
    }

    /** @var array<string, mixed> $payload */
    $payload = $decoded;

    return $payload;
  }
}
