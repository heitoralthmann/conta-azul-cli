<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/**
 * Paths the local OAuth callback listener uses for TLS, when it uses TLS.
 *
 * `caFile` is present only for material this CLI issued itself: that is the
 * CA that has to land in the user trust store so the browser accepts the
 * redirect. Operator-supplied certs leave it null — they brought their own
 * trust arrangement.
 */
final class CallbackTlsMaterial
{
  /**
   * @param string|null $certFile Leaf certificate path, or null for plain HTTP.
   * @param string|null $keyFile  Leaf private key path, or null for plain HTTP.
   * @param string|null $caFile   Local CA path when this CLI generated the leaf.
   */
  public function __construct(
      public readonly string|null $certFile,
      public readonly string|null $keyFile,
      public readonly string|null $caFile = null,
  ) {
  }

  /** Whether the callback listener should open a TLS socket. */
  public function usesTls(): bool {
    return $this->certFile !== null && $this->keyFile !== null;
  }
}
