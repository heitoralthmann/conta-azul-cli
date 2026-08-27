<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Installs a local CA into the current user's certificate trust store. */
interface TrustStoreInstaller
{
  /**
   * Makes `$caCertificatePath` trusted for TLS server authentication.
   *
   * Idempotent: a CA that is already trusted is left alone, so login does
   * not re-prompt for a keychain password every time.
   */
  public function ensureTrusted(string $caCertificatePath): void;
}
