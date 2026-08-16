<?php

declare(strict_types=1);

namespace ContaAzulCli\Output;

use ContaAzulCli\Config\HomeDirectory;

/** Writes optional structured JSONL diagnostics without affecting command output. */
final class Logger
{
  private bool $enabled = FALSE;
  private ?string $logPath = NULL;
  private const MAX_SIZE = 10 * 1024 * 1024;
  private const MAX_FILES = 3;


  /** Creates a logger that redacts sensitive values before persistence. */
  public function __construct(private readonly Redactor $redactor) {
  }


  /** Enables file logging and rotates an oversized existing log. */
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


  /** Reports whether logging has been enabled for this process. */
  public function isEnabled(): bool {
    return $this->enabled;
  }


  /**
   * Appends one structured log event when logging is enabled.
   *
   * @param array<mixed> $context Values associated with the event.
   */
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


  /** Moves older log files aside when the active file exceeds its limit. */
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
