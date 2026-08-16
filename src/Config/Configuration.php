<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

/**
 * Immutable, validated runtime configuration for the CLI.
 *
 * The preferred construction path is {@see EnvironmentConfigurationLoader}.
 * The no-argument constructor remains supported for existing integrations and
 * delegates environment access to that loader.
 */
final class Configuration
{
  /** Client identifier used for OAuth requests. */
  private readonly string $clientId;
  /** Client secret used for OAuth requests. */
  private readonly string $clientSecret;
  /** OAuth redirect URI. */
  private readonly string $redirectUri;
  /** Optional OAuth scope. */
  private readonly ?string $scope;
  /** Conta Azul API base URL. */
  private readonly string $apiBaseUrl;
  /** OAuth authorization server base URL. */
  private readonly string $authBaseUrl;
  /** OAuth authorization endpoint URL. */
  private readonly string $authorizeUrl;
  /** OAuth token endpoint URL. */
  private readonly string $tokenUrl;
  /** File path used to persist tokens. */
  private readonly string $tokenPath;
  /** Optional bootstrap refresh token. */
  private readonly ?string $bootstrapRefreshToken;
  /** Optional callback TLS certificate path. */
  private readonly ?string $callbackCertFile;
  /** Optional callback TLS key path. */
  private readonly ?string $callbackKeyFile;
  /** Callback server timeout in seconds. */
  private readonly int $callbackTimeout;


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
  public function __construct(?array $values=NULL) {
    if ($values === NULL) {
      $values = (new EnvironmentConfigurationLoader())->read();
    }

    $this->clientId = $values['clientId'];
    $this->clientSecret = $values['clientSecret'];
    $this->redirectUri = $values['redirectUri'];
    $this->scope = $values['scope'];
    $this->apiBaseUrl = $values['apiBaseUrl'];
    $this->authBaseUrl = $values['authBaseUrl'];
    $this->authorizeUrl = $values['authorizeUrl'];
    $this->tokenUrl = $values['tokenUrl'];
    $this->tokenPath = $values['tokenPath'];
    $this->bootstrapRefreshToken = $values['bootstrapRefreshToken'];
    $this->callbackCertFile = $values['callbackCertFile'];
    $this->callbackKeyFile = $values['callbackKeyFile'];
    $this->callbackTimeout = $values['callbackTimeout'];
  }


  /**
   * Creates a configuration by reading the process environment.
   *
   * @param EnvironmentConfigurationLoader|null $loader Optional loader for
   *                                                       deterministic tests.
   *
   * @throws ConfigException When a required value is missing.
   */
  public static function fromEnvironment(?EnvironmentConfigurationLoader $loader=NULL): self {
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


  /** Returns the OAuth client identifier. */
  public function getClientId(): string {
    return $this->clientId;
  }


  /** Returns the OAuth client secret. */
  public function getClientSecret(): string {
    return $this->clientSecret;
  }


  /** Returns the OAuth redirect URI. */
  public function getRedirectUri(): string {
    return $this->redirectUri;
  }


  /** Returns the Conta Azul API base URL without a trailing slash. */
  public function getApiBaseUrl(): string {
    return $this->apiBaseUrl;
  }


  /** Returns the OAuth service base URL without a trailing slash. */
  public function getAuthBaseUrl(): string {
    return $this->authBaseUrl;
  }


  /** Returns the complete OAuth authorization endpoint URL. */
  public function getAuthorizeUrl(): string {
    return $this->authorizeUrl;
  }


  /** Returns the complete OAuth token endpoint URL. */
  public function getTokenUrl(): string {
    return $this->tokenUrl;
  }


  /** Returns the expanded local token file path. */
  public function getTokenPath(): string {
    return $this->tokenPath;
  }


  /** Returns the optional bootstrap refresh token. */
  public function getBootstrapRefreshToken(): ?string {
    return $this->bootstrapRefreshToken;
  }


  /** Returns the optional OAuth scope. */
  public function getScope(): ?string {
    return $this->scope;
  }


  /** Returns the optional TLS callback certificate path. */
  public function getCallbackCertFile(): ?string {
    return $this->callbackCertFile;
  }


  /** Returns the optional TLS callback private key path. */
  public function getCallbackKeyFile(): ?string {
    return $this->callbackKeyFile;
  }


  /** Returns the callback server timeout in seconds. */
  public function getCallbackTimeout(): int {
    return $this->callbackTimeout;
  }


}
