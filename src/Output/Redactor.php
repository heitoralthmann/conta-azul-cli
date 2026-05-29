<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

final class Redactor
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'client_secret',
        'access_token',
        'refresh_token',
        'ca_bootstrap_refresh_token',
        'password',
    ];

    /** @param array<mixed> $data */
    public function redact(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function redactString(string $value): string
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            $value = (string) preg_replace(
                '/(\"' . preg_quote($key, '/') . '\"\s*:\s*\")[^\"]*(\")/',
                '$1[REDACTED]$2',
                $value,
            );
        }

        return $value;
    }
}
