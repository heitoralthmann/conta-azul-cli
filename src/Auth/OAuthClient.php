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
    ) {
    }

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
        $isCodeExchange = ($body['grant_type'] ?? '') === 'authorization_code';

        $credentials = base64_encode(
            $this->config->getClientId() . ':' . $this->config->getClientSecret(),
        );

        try {
            $response = $this->httpClient->request('POST', $this->config->getTokenUrl(), [
                'headers' => [
                    'Authorization' => "Basic {$credentials}",
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($body),
            ]);
            $data = $response->toArray();
        } catch (\Symfony\Component\HttpClient\Exception\ClientException $e) {
            $errorCode = '';
            $detail    = '';
            try {
                $errData     = $e->getResponse()->toArray(false);
                $rawCode     = $errData['error'] ?? '';
                $errorCode   = is_string($rawCode) ? $rawCode : '';
                $description = $errData['error_description'] ?? '';
                $detail      = is_string($description) ? $description : '';
            } catch (\Throwable) {
            }

            $status = $e->getResponse()->getStatusCode();
            $suffix = $this->formatDetail($errorCode, $detail, $status);

            if ($errorCode === 'invalid_grant') {
                // The same error code means opposite things per grant type, and
                // telling the operator to re-login when the *code* just expired
                // sends them in circles.
                $message = $isCodeExchange
                    ? 'Código de autorização inválido, expirado ou já utilizado. '
                        . 'Rode "ca auth login" e conclua o login no navegador sem reaproveitar URLs antigas.'
                    : 'Refresh token inválido ou expirado. Execute: ca auth login';

                throw new CliException(ErrorKind::AuthFailed, false, $message . $suffix, $status, previous: $e);
            }

            throw new CliException(
                ErrorKind::AuthFailed,
                false,
                "Falha na autenticação (HTTP {$status}). Execute: ca auth login" . $suffix,
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

        /** @var array<string, mixed> $data */
        return TokenData::fromOAuthResponse($data);
    }

    /**
     * The provider's own error/error_description is the only thing that
     * distinguishes an expired code from a redirect_uri mismatch, so it is
     * carried into the envelope instead of being swallowed.
     */
    private function formatDetail(string $errorCode, string $detail, int $status): string
    {
        $parts = array_filter([$errorCode, $detail]);
        if ($parts === []) {
            return " (HTTP {$status}, sem detalhe do provedor)";
        }

        return ' Provedor respondeu: ' . implode(' — ', $parts);
    }
}
