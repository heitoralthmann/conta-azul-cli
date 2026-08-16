<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

use function ctype_digit;
use function getenv;
use function rtrim;
use function str_starts_with;
use function substr;

/**
 * Reads and validates configuration values from the process environment.
 *
 * Keeping environment access here means the configuration value object can be
 * created from already validated values in tests and other entry points.
 */
final class EnvironmentConfigurationLoader
{
  /**
   * Loads the current process environment into an immutable configuration.
   *
   * @throws ConfigException When a required value is missing or a home path
   *                         cannot be resolved.
   */
  public function load(): Configuration {
    return new Configuration($this->read());
  }

  /**
   * Reads, validates, and normalizes all supported environment variables.
   *
   * @return array{
   *     clientId: string,
   *     clientSecret: string,
   *     redirectUri: string,
   *     scope: ?string,
   *     apiBaseUrl: string,
   *     authBaseUrl: string,
   *     authorizeUrl: string,
   *     tokenUrl: string,
   *     tokenPath: string,
   *     bootstrapRefreshToken: ?string,
   *     callbackCertFile: ?string,
   *     callbackKeyFile: ?string,
   *     callbackTimeout: int
   * }
   *
   * @throws ConfigException When a required value is missing or a home path
   *                         cannot be resolved.
   */
  public function read(): array {
    $authBaseUrl = rtrim($this->getEnv('CA_AUTH_BASE_URL', 'https://auth.contaazul.com'), '/');

    return [
      'clientId' => $this->requireEnv('CA_CLIENT_ID'),
      'clientSecret' => $this->requireEnv('CA_CLIENT_SECRET'),
      'redirectUri' => $this->getEnv('CA_REDIRECT_URI', 'http://localhost:9876/callback'),
      'scope' => $this->nullableEnv('CA_SCOPE'),
      'apiBaseUrl' => rtrim($this->getEnv('CA_API_BASE_URL', 'https://api-v2.contaazul.com'), '/'),
      'authBaseUrl' => $authBaseUrl,
          // Authorization and token endpoints may intentionally use
          // different hosts and paths in production environments.
      'authorizeUrl' => $this->getEnv('CA_AUTHORIZE_URL', $authBaseUrl . '/oauth2/authorize'),
      'tokenUrl' => $this->getEnv('CA_TOKEN_URL', $authBaseUrl . '/oauth2/token'),
      'tokenPath' => $this->expandHome($this->getEnv('CA_CLI_TOKEN_PATH', '~/.config/conta-azul-cli/tokens.json')),
      'bootstrapRefreshToken' => $this->nullableEnv('CA_BOOTSTRAP_REFRESH_TOKEN'),
      'callbackCertFile' => $this->nullableExpandedEnv('CA_CALLBACK_CERT'),
      'callbackKeyFile' => $this->nullableExpandedEnv('CA_CALLBACK_KEY'),
      'callbackTimeout' => $this->callbackTimeout(),
    ];
  }

  /**
   * Returns a required non-empty environment variable.
   *
   * @throws ConfigException When the variable is absent or empty.
   */
  private function requireEnv(string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
      throw new ConfigException(
          'Variável de ambiente obrigatória não definida: ' . $name
              . '. Configure em .env ou exporte antes de executar.',
      );
    }

    return $value;
  }

  /**
   * Returns an environment variable or its default when absent or empty.
   */
  private function getEnv(string $name, string $default): string {
    $value = getenv($name);

    return $value !== false && $value !== '' ? $value : $default;
  }

  /**
   * Returns a nullable environment variable, treating empty as absent.
   */
  private function nullableEnv(string $name): string|null {
    $value = getenv($name);

    return $value !== false && $value !== '' ? $value : null;
  }

  /**
   * Returns a nullable environment path after expanding a leading tilde.
   *
   * @throws ConfigException When the home directory cannot be determined.
   */
  private function nullableExpandedEnv(string $name): string|null {
    $value = $this->nullableEnv($name);

    return $value === null ? null : $this->expandHome($value);
  }

  /**
   * Reads the callback timeout, falling back to five minutes when invalid.
   */
  private function callbackTimeout(): int {
    $timeout = $this->getEnv('CA_CALLBACK_TIMEOUT', '300');

    return ctype_digit($timeout) && (int) $timeout > 0 ? (int) $timeout : 300;
  }

  /**
   * Expands a leading `~/` using the platform home directory.
   *
   * @throws ConfigException When the home directory cannot be determined.
   */
  private function expandHome(string $path): string {
    if (! str_starts_with($path, '~/')) {
      return $path;
    }

    $home = HomeDirectory::resolve();
    if ($home === null) {
      throw new ConfigException(
          'Não foi possível determinar o diretório home do usuário. '
              . 'Defina CA_CLI_TOKEN_PATH com um caminho absoluto.',
      );
    }

    return $home . substr($path, 1);
  }
}
