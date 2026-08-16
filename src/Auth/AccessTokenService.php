<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

/** Resolves, refreshes, and persists access tokens behind one cohesive boundary. */
final class AccessTokenService implements AccessTokenServiceInterface
{


    /**
     * Creates the token lifecycle service.
     *
     * @param TokenRepositoryInterface $tokenRepository Local token persistence.
     * @param OAuthGatewayInterface $oauthGateway Remote OAuth exchange adapter.
     * @param Configuration $config Runtime configuration, including bootstrap token.
     * @param TokenLockInterface $tokenLock Cross-process refresh coordinator.
     */
    public function __construct(
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly OAuthGatewayInterface $oauthGateway,
        private readonly Configuration $config,
        private readonly TokenLockInterface $tokenLock,
    ) {
    }


    /** Returns a usable access token, refreshing it when it is expiring soon. */
    public function getValidAccessToken(): string {
        return $this->tokenLock->synchronized(fn (): string => $this->resolveToken());
    }


    /** Refreshes and persists the token after an API 401 response. */
    public function refreshAfter401(): string {
        return $this->tokenLock->synchronized(
          function (): string {
            $token = $this->tokenRepository->load();
            if ($token === NULL) {
                throw $this->notAuthenticated();
            }

            $newToken = $this->oauthGateway->refresh($token->refreshToken);
            $this->tokenRepository->save($newToken);

            return $newToken->accessToken;
          }
        );
    }


    /** Removes the locally cached credentials. */
    public function logout(): void {
        $this->tokenRepository->delete();
    }


    /** Resolves a token while the caller holds the refresh lock. */
    private function resolveToken(): string {
        $bootstrap = $this->config->getBootstrapRefreshToken();
        if ($bootstrap !== NULL) {
            $token = $this->oauthGateway->refresh($bootstrap);
            $this->tokenRepository->save($token);

            return $token->accessToken;
        }

        $token = $this->tokenRepository->load();
        if ($token === NULL) {
            throw $this->notAuthenticated();
        }

        if ($token->isExpiringSoon()) {
            $token = $this->oauthGateway->refresh($token->refreshToken);
            $this->tokenRepository->save($token);
        }

        return $token->accessToken;
    }


    /** Creates the stable error returned when no local token exists. */
    private function notAuthenticated(): CliException {
        return new CliException(
          ErrorKind::AuthFailed,
          FALSE,
          'Não autenticado. Execute: ca auth login',
        );
    }


}
