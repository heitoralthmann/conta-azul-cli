<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Ramsey\Uuid\Uuid;

final class AuthManager
{
    private ?string $pendingState = NULL;


    public function __construct(
        private readonly TokenStore $tokenStore,
        private readonly OAuthClient $oauthClient,
        private readonly Configuration $config,
    ) {
    }


    public function getValidAccessToken(): string {
        $lock = $this->acquireLock();
        try {
            return $this->resolveToken();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }


    private function resolveToken(): string {
        $bootstrap = $this->config->getBootstrapRefreshToken();
        if ($bootstrap !== NULL) {
            $token = $this->oauthClient->refresh($bootstrap);
            $this->tokenStore->save($token);

            return $token->accessToken;
        }

        $token = $this->tokenStore->load();
        if ($token === NULL) {
            throw new CliException(
              ErrorKind::AuthFailed,
              FALSE,
              'Não autenticado. Execute: ca auth login',
            );
        }

        if ($token->isExpiringSoon()) {
            $token = $this->oauthClient->refresh($token->refreshToken);
            $this->tokenStore->save($token);
        }

        return $token->accessToken;
    }


    public function refreshAfter401(): string {
        $lock = $this->acquireLock();
        try {
            $token = $this->tokenStore->load();
            if ($token === NULL) {
                throw new CliException(
                  ErrorKind::AuthFailed,
                  FALSE,
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


    public function startLoginFlow(): string {
        $this->pendingState = Uuid::uuid4()->toString();

        $params = array_filter(
          [
            'response_type' => 'code',
            'client_id'     => $this->config->getClientId(),
            'redirect_uri'  => $this->config->getRedirectUri(),
            'scope'         => $this->config->getScope(),
            'state'         => $this->pendingState,
          ]
        );

        // O endpoint de autorização precisa pertencer ao mesmo servidor que
        // emite os tokens: o code só é resgatável em quem o emitiu. A query é
        // anexada como texto para preservar qualquer formato de URL que o
        // provedor exija, inclusive rotas de fragmento.
        $separator = str_contains($this->config->getAuthorizeUrl(), '?') ? '&' : '?';

        return $this->config->getAuthorizeUrl().$separator.http_build_query($params);
    }


    public function getPendingState(): ?string {
        return $this->pendingState;
    }


    public function completeLoginFlow(string $code): void {
        $token = $this->oauthClient->exchangeCode($code);
        $this->tokenStore->save($token);
        $this->pendingState = NULL;
    }


    public function logout(): void {
        $this->tokenStore->delete();
    }


    /**
     * Serializes refresh across concurrent invocations: whoever loses the race
     * waits here and then reads the token the winner already rotated.
     *
     * @return resource
     */
    private function acquireLock(): mixed {
        $lockFile = $this->tokenStore->getPath().'.lock';
        $lockDir  = dirname($lockFile);
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0700, TRUE);
        }

        // Silenced: a missing/unreadable lock file is handled explicitly below
        // via the FALSE check, so the PHP warning would only be noise.
        // phpcs:ignore Generic.PHP.NoSilencedErrors
        $lock = @fopen($lockFile, 'c');
        if ($lock === FALSE) {
            throw new CliException(
              ErrorKind::AuthFailed,
              FALSE,
              'Não foi possível adquirir lock do arquivo de tokens.',
            );
        }

        flock($lock, LOCK_EX);

        return $lock;
    }


}
