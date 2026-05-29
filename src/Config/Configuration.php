<?php

declare(strict_types=1);

namespace ContaAzulCli\Config;

final class Configuration
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $apiBaseUrl;
    private string $authBaseUrl;
    private string $tokenPath;
    private ?string $bootstrapRefreshToken;

    public function __construct()
    {
        $this->clientId = $this->requireEnv('CA_CLIENT_ID');
        $this->clientSecret = $this->requireEnv('CA_CLIENT_SECRET');
        $this->redirectUri = $this->getEnv('CA_REDIRECT_URI', 'http://localhost:9876/callback');
        $this->apiBaseUrl = rtrim($this->getEnv('CA_API_BASE_URL', 'https://api-v2.contaazul.com'), '/');
        $this->authBaseUrl = rtrim($this->getEnv('CA_AUTH_BASE_URL', 'https://auth.contaazul.com'), '/');
        $rawPath = $this->getEnv('CA_CLI_TOKEN_PATH', '~/.config/conta-azul-cli/tokens.json');
        $this->tokenPath = $this->expandHome($rawPath);
        $bootstrap = getenv('CA_BOOTSTRAP_REFRESH_TOKEN');
        $this->bootstrapRefreshToken = ($bootstrap !== false && $bootstrap !== '') ? $bootstrap : null;
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

    public function getTokenPath(): string
    {
        return $this->tokenPath;
    }

    public function getBootstrapRefreshToken(): ?string
    {
        return $this->bootstrapRefreshToken;
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

        $home = getenv('HOME');
        if ($home === false || $home === '') {
            $entry = posix_getpwuid(posix_getuid());
            $home = is_array($entry) ? $entry['dir'] : '/root';
        }

        return $home . substr($path, 1);
    }
}
