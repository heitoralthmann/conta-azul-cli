<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

/** Exchanges authorization codes and refresh tokens with the OAuth provider. */
interface OAuthGatewayInterface
{


    /** Exchanges a one-time authorization code for an access token. */
    public function exchangeCode(string $code): TokenData;


    /** Exchanges a refresh token for a new access token and token rotation. */
    public function refresh(string $refreshToken): TokenData;


}
