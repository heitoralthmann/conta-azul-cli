<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

final class Configuration
{
    private string  $clientId;
    private string  $clientSecret;
    private string  $redirectUri;
    private ?string $scope;
    private string  $apiBaseUrl;
    private string  $authBaseUrl;
    private string  $authorizeUrl;
    private string  $tokenUrl;
    private string  $tokenPath;
    private ?string $bootstrapRefreshToken;
    private ?string $callbackCertFile;
    private ?string $callbackKeyFile;
    private int     $callbackTimeout;

    public function __construct()
    {
        $this->clientId   = $this->requireEnv('CA_CLIENT_ID');
        $this->clientSecret = $this->requireEnv('CA_CLIENT_SECRET');
        $this->redirectUri  = $this->getEnv('CA_REDIRECT_URI', 'http://localhost:9876/callback');
        $scope              = getenv('CA_SCOPE');
        $this->scope        = ($scope !== false && $scope !== '') ? $scope : null;
        $this->apiBaseUrl   = rtrim($this->getEnv('CA_API_BASE_URL', 'https://api-v2.contaazul.com'), '/');
        $this->authBaseUrl  = rtrim($this->getEnv('CA_AUTH_BASE_URL', 'https://auth.contaazul.com'), '/');
        // Apps de produção usam um endpoint de autorização distinto do de token,
        // com host e path próprios — daí ser configurável por inteiro.
        $this->authorizeUrl = $this->getEnv('CA_AUTHORIZE_URL', $this->authBaseUrl . '/oauth2/authorize');
        $this->tokenUrl     = $this->getEnv('CA_TOKEN_URL', $this->authBaseUrl . '/oauth2/token');
        $rawPath            = $this->getEnv('CA_CLI_TOKEN_PATH', '~/.config/conta-azul-cli/tokens.json');
        $this->tokenPath    = $this->expandHome($rawPath);

        $bootstrap                  = getenv('CA_BOOTSTRAP_REFRESH_TOKEN');
        $this->bootstrapRefreshToken = ($bootstrap !== false && $bootstrap !== '') ? $bootstrap : null;

        $cert                    = getenv('CA_CALLBACK_CERT');
        $this->callbackCertFile  = ($cert !== false && $cert !== '') ? $this->expandHome($cert) : null;
        $key                     = getenv('CA_CALLBACK_KEY');
        $this->callbackKeyFile   = ($key !== false && $key !== '') ? $this->expandHome($key) : null;

        $timeout               = $this->getEnv('CA_CALLBACK_TIMEOUT', '300');
        $this->callbackTimeout = ctype_digit($timeout) && (int) $timeout > 0 ? (int) $timeout : 300;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function getAuthBaseUrl(): string
    {
        return $this->authBaseUrl;
    }

    public function getAuthorizeUrl(): string
    {
        return $this->authorizeUrl;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function getTokenPath(): string
    {
        return $this->tokenPath;
    }

    public function getBootstrapRefreshToken(): ?string
    {
        return $this->bootstrapRefreshToken;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getCallbackCertFile(): ?string
    {
        return $this->callbackCertFile;
    }

    public function getCallbackKeyFile(): ?string
    {
        return $this->callbackKeyFile;
    }

    public function getCallbackTimeout(): int
    {
        return $this->callbackTimeout;
    }

    private function requireEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new ConfigException("Variável de ambiente obrigatória não definida: {$name}. Configure em .env ou exporte antes de executar.");
        }

        return $value;
    }

    private function getEnv(string $name, string $default): string
    {
        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : $default;
    }

    private function expandHome(string $path): string
    {
        if (!str_starts_with($path, '~/')) {
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
