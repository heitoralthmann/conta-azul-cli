<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

/**
 * Immutable, validated runtime configuration for the CLI.
 *
 * The preferred construction path is {@see EnvironmentConfigurationLoader}.
 * The no-argument constructor remains supported for existing integrations and
 * delegates environment access to that loader.
 *
 * @property-read string $clientId OAuth client identifier.
 * @property-read string $clientSecret OAuth client secret.
 * @property-read string $redirectUri OAuth redirect URI.
 * @property-read string|null $scope Optional OAuth scope.
 * @property-read string $apiBaseUrl Conta Azul API base URL, without a trailing slash.
 * @property-read string $authBaseUrl OAuth authorization server base URL, without a trailing slash.
 * @property-read string $authorizeUrl Complete OAuth authorization endpoint URL.
 * @property-read string $tokenUrl Complete OAuth token endpoint URL.
 * @property-read string $tokenPath Expanded local token file path.
 * @property-read string|null $bootstrapRefreshToken Optional bootstrap refresh token.
 * @property-read string|null $callbackCertFile Optional TLS callback certificate path.
 * @property-read string|null $callbackKeyFile Optional TLS callback private key path.
 * @property-read int $callbackTimeout Callback server timeout in seconds.
 */
final class Configuration
{
  /** Client identifier used for OAuth requests. */
  public readonly string $clientId;
  /** Client secret used for OAuth requests. */
  public readonly string $clientSecret;
  /** OAuth redirect URI. */
  public readonly string $redirectUri;
  /** Optional OAuth scope. */
  public readonly string|null $scope;
  /** Conta Azul API base URL. */
  public readonly string $apiBaseUrl;
  /** OAuth authorization server base URL. */
  public readonly string $authBaseUrl;
  /** OAuth authorization endpoint URL. */
  public readonly string $authorizeUrl;
  /** OAuth token endpoint URL. */
  public readonly string $tokenUrl;
  /** File path used to persist tokens. */
  public readonly string $tokenPath;
  /** Optional bootstrap refresh token. */
  public readonly string|null $bootstrapRefreshToken;
  /** Optional callback TLS certificate path. */
  public readonly string|null $callbackCertFile;
  /** Optional callback TLS key path. */
  public readonly string|null $callbackKeyFile;
  /** Callback server timeout in seconds. */
  public readonly int $callbackTimeout;

  /**
   * Creates a configuration value object from normalized values.
   *
   * Calling this constructor without values is retained as a backwards
   * compatible convenience and reads the process environment through
   * {@see EnvironmentConfigurationLoader}.
   *
   * @param array{
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
   * }|null $values
   *
   * @throws ConfigException When the environment is used and required
   *                         values are missing.
   */
  public function __construct(array|null $values = null) {
    if ($values === null) {
      $values = (new EnvironmentConfigurationLoader())->read();
    }

    $this->clientId              = $values['clientId'];
    $this->clientSecret          = $values['clientSecret'];
    $this->redirectUri           = $values['redirectUri'];
    $this->scope                 = $values['scope'];
    $this->apiBaseUrl            = $values['apiBaseUrl'];
    $this->authBaseUrl           = $values['authBaseUrl'];
    $this->authorizeUrl          = $values['authorizeUrl'];
    $this->tokenUrl              = $values['tokenUrl'];
    $this->tokenPath             = $values['tokenPath'];
    $this->bootstrapRefreshToken = $values['bootstrapRefreshToken'];
    $this->callbackCertFile      = $values['callbackCertFile'];
    $this->callbackKeyFile       = $values['callbackKeyFile'];
    $this->callbackTimeout       = $values['callbackTimeout'];
  }

  /**
   * Creates a configuration by reading the process environment.
   *
   * @param EnvironmentConfigurationLoader|null $loader Optional loader for
   *                                                       deterministic tests.
   *
   * @throws ConfigException When a required value is missing.
   */
  public static function fromEnvironment(EnvironmentConfigurationLoader|null $loader = null): self {
    return ($loader ?? new EnvironmentConfigurationLoader())->load();
  }

  /**
   * Creates a configuration from normalized values.
   *
   * @param array{
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
   * } $values
   */
  public static function fromValues(array $values): self {
    return new self($values);
  }
}
