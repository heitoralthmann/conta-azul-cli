<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Ramsey\Uuid\Uuid;

final class AuthManager
{
    private ?string $pendingState = null;

    public function __construct(
        private readonly TokenStore $tokenStore,
        private readonly OAuthClient $oauthClient,
        private readonly Configuration $config,
    ) {}

    public function getValidAccessToken(): string
    {
        $lockFile = $this->tokenStore->getPath() . '.lock';
        $lockDir = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0700, true);
        }

        $lock = fopen($lockFile, 'c');
        if ($lock === false) {
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                'Não foi possível adquirir lock do arquivo de tokens.',
            );
        }

        flock($lock, LOCK_EX);
        try {
            return $this->resolveToken();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function resolveToken(): string
    {
        $bootstrap = $this->config->getBootstrapRefreshToken();
        if ($bootstrap !== null) {
            $token = $this->oauthClient->refresh($bootstrap);
            $this->tokenStore->save($token);

            return $token->accessToken;
        }

        $token = $this->tokenStore->load();
        if ($token === null) {
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                'Não autenticado. Execute: ca auth login',
            );
        }

        if ($token->isExpiringSoon()) {
            $token = $this->oauthClient->refresh($token->refreshToken);
            $this->tokenStore->save($token);
        }

        return $token->accessToken;
    }

    public function refreshAfter401(): string
    {
        $lockFile = $this->tokenStore->getPath() . '.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false) {
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                'Não foi possível adquirir lock do arquivo de tokens.',
            );
        }

        flock($lock, LOCK_EX);
        try {
            $token = $this->tokenStore->load();
            if ($token === null) {
                throw new CliException(
                    ErrorKind::AuthFailed,
                    false,
                    'Não autenticado. Execute: ca auth login',
                );
            }
            $newToken = $this->oauthClient->refresh($token->refreshToken);
            $this->tokenStore->save($newToken);

            return $newToken->accessToken;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function startLoginFlow(): string
    {
        $this->pendingState = Uuid::uuid4()->toString();

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->config->getClientId(),
            'redirect_uri'  => $this->config->getRedirectUri(),
            'scope'         => 'financeiro',
            'state'         => $this->pendingState,
        ]);

        return $this->config->getAuthBaseUrl() . '/oauth2/authorize?' . $params;
    }

    public function getPendingState(): ?string
    {
        return $this->pendingState;
    }

    public function completeLoginFlow(string $code): void
    {
        $token = $this->oauthClient->exchangeCode($code);
        $this->tokenStore->save($token);
        $this->pendingState = null;
    }

    public function logout(): void
    {
        $this->tokenStore->delete();
    }
}
