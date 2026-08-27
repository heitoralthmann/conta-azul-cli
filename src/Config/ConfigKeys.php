<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function array_key_exists;
use function array_keys;

/**
 * The environment variables the CLI understands, with their compiled defaults.
 *
 * `ca config set` validates against this list so a typo becomes an immediate
 * error instead of a variable that silently never takes effect, and
 * `ca config show` walks it to report the effective configuration.
 *
 * The defaults mirror {@see EnvironmentConfigurationLoader}; two of them are
 * derived from `CA_AUTH_BASE_URL` at load time and are shown here in the
 * `{VARIABLE}` form the documentation already uses.
 *
 * `CA_CLI_ENV_FILE` is deliberately absent: it *selects* the environment file
 * rather than living inside one, so setting it there could never take effect.
 * `ca config path` is where its value surfaces.
 */
final class ConfigKeys
{
  public const array DEFAULTS = [
    'CA_API_BASE_URL'            => 'https://api-v2.contaazul.com',
    'CA_AUTHORIZE_URL'           => '{CA_AUTH_BASE_URL}/oauth2/authorize',
    'CA_AUTH_BASE_URL'           => 'https://auth.contaazul.com',
    'CA_BOOTSTRAP_REFRESH_TOKEN' => null,
    'CA_CALLBACK_CERT'           => null,
    'CA_CALLBACK_KEY'            => null,
    'CA_CALLBACK_TIMEOUT'        => '300',
    'CA_CLIENT_ID'               => null,
    'CA_CLIENT_SECRET'           => null,
    'CA_CLI_TOKEN_PATH'          => '~/.config/conta-azul-cli/tokens.json',
    'CA_REDIRECT_URI'            => 'https://conta-azul-cli.ddev.site:9876/callback',
    'CA_SCOPE'                   => null,
    'CA_TOKEN_URL'               => '{CA_AUTH_BASE_URL}/oauth2/token',
  ];

  /**
   * Every known variable name, in the order `ca config show` prints them.
   *
   * @return list<string>
   */
  public static function names(): array {
    return array_keys(self::DEFAULTS);
  }

  /** Whether this name is a variable the CLI actually reads. */
  public static function isKnown(string $name): bool {
    return array_key_exists($name, self::DEFAULTS);
  }

  /** Returns the compiled default for a known variable, or null when it has none. */
  public static function default(string $name): string|null {
    return self::DEFAULTS[$name] ?? null;
  }
}
