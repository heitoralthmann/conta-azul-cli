<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Config\HomeDirectory;

final class Logger
{
    private bool $enabled = FALSE;
    private ?string $logPath = NULL;
    private const MAX_SIZE = 10 * 1024 * 1024;
    private const MAX_FILES = 3;


    public function __construct(private readonly Redactor $redactor) {
    }


    public function enable(): void {
        // Logging is best-effort: an unresolvable home must not break the run,
        // so it degrades to the system temp directory instead of throwing.
        $home     = HomeDirectory::resolve() ?? sys_get_temp_dir();
        $cacheDir = $home . '/.cache/conta-azul-cli';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700, TRUE);
        }
        $this->logPath = $cacheDir . '/log.jsonl';
        $this->enabled = TRUE;
        $this->rotate();
    }


    public function isEnabled(): bool {
        return $this->enabled;
    }


    /** @param array<mixed> $context */
    public function log(string $level, string $message, array $context=[], string $correlationId=''): void {
        if (!$this->enabled || $this->logPath === NULL) {
            return;
        }

        $entry = json_encode(
          [
            'timestamp'      => gmdate('Y-m-d\TH:i:s\Z'),
            'level'          => $level,
            'message'        => $message,
            'context'        => $this->redactor->redact($context),
            'correlation_id' => $correlationId,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . "\n";

        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }


    private function rotate(): void {
        if ($this->logPath === NULL || !file_exists($this->logPath)) {
            return;
        }
        if (filesize($this->logPath) < self::MAX_SIZE) {
            return;
        }
        for ($i = self::MAX_FILES - 1; $i >= 1; $i--) {
            $old = $this->logPath . '.' . $i;
            $new = $this->logPath . '.' . ($i + 1);
            if (file_exists($old)) {
                rename($old, $new);
            }
        }
        rename($this->logPath, $this->logPath . '.1');
    }


}
