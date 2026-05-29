<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OAuthClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Configuration $config,
    ) {}

    public function exchangeCode(string $code): TokenData
    {
        return $this->requestToken([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->config->getRedirectUri(),
        ]);
    }

    public function refresh(string $refreshToken): TokenData
    {
        return $this->requestToken([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /** @param array<string, string> $body */
    private function requestToken(array $body): TokenData
    {
        $credentials = base64_encode(
            $this->config->getClientId() . ':' . $this->config->getClientSecret(),
        );

        try {
            $response = $this->httpClient->request('POST', $this->config->getAuthBaseUrl() . '/oauth2/token', [
                'headers' => [
                    'Authorization' => "Basic {$credentials}",
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($body),
            ]);
            $data = $response->toArray();
        } catch (\Symfony\Component\HttpClient\Exception\ClientException $e) {
            $errorCode = '';
            try {
                $errData = $e->getResponse()->toArray(false);
                $errorCode = (string) ($errData['error'] ?? '');
            } catch (\Throwable) {
            }

            if ($errorCode === 'invalid_grant') {
                throw new CliException(
                    ErrorKind::AuthFailed,
                    false,
                    'Refresh token inválido ou expirado. Execute: ca auth login',
                    previous: $e,
                );
            }

            $status = $e->getResponse()->getStatusCode();
            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                "Falha na autenticação (HTTP {$status}). Execute: ca auth login",
                $status,
                previous: $e,
            );
        } catch (\Throwable $e) {
            throw new CliException(
                ErrorKind::Transient,
                true,
                "Erro de rede ao autenticar: {$e->getMessage()}",
                previous: $e,
            );
        }

        return TokenData::fromOAuthResponse($data);
    }
}
