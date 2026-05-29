<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

final class JsonRenderer
{
    public function render(mixed $data): void
    {
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
