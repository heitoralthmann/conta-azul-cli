<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

/**
 * Avisos vão para stderr em envelope JSON, pela mesma razão que os erros: o
 * stdout é reservado ao payload, e quem consome o CLI num pipeline precisa
 * conseguir ler o aviso por máquina, não só por olho.
 */
final class WarningEnvelope
{


    public function renderToStderr(string $message): void {
        fwrite(
          STDERR, json_encode(
            ['kind' => 'warning', 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
          ) . "\n"
        );
    }


}
