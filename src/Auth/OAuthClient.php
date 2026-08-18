<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function array_filter;
use function base64_encode;
use function http_build_query;
use function implode;
use function is_string;
use function parse_url;
use function sprintf;

use const PHP_URL_HOST;

/** Symfony HTTP adapter for Conta Azul's OAuth token endpoint. */
final class OAuthClient implements OAuthGatewayInterface
{
  /** Creates an OAuth adapter using the shared HTTP client and configuration. */
  public function __construct(
      private readonly HttpClientInterface $httpClient,
      private readonly Configuration $config,
  ) {
  }

  /** Exchanges an authorization code for a token. */
  public function exchangeCode(string $code): TokenData {
    return $this->requestToken(
        [
          'code'         => $code,
          'grant_type'   => 'authorization_code',
          'redirect_uri' => $this->config->redirectUri,
        ],
    );
  }

  /** Exchanges a refresh token for a new (possibly rotated) token. */
  public function refresh(string $refreshToken): TokenData {
    return $this->requestToken(
        [
          'grant_type'    => 'refresh_token',
          'refresh_token' => $refreshToken,
        ],
    );
  }

  /** @param array<string, string> $body */
  private function requestToken(array $body): TokenData {
    $isCodeExchange = ($body['grant_type'] ?? '') === 'authorization_code';

    $credentials = base64_encode(
        $this->config->clientId . ':' . $this->config->clientSecret,
    );

    try {
      $response = $this->httpClient->request(
          'POST',
          $this->config->tokenUrl,
          [
            'body'    => http_build_query($body),
            'headers' => [
              'Authorization' => 'Basic ' . $credentials,
              'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
          ],
      );
      /** @var array<string, mixed> $data */
      $data = $response->toArray();
    } catch (ClientException $e) {
      $errorCode = '';
      $detail    = '';
      try {
        $errData     = $e->getResponse()->toArray(false);
        $rawCode     = $errData['error'] ?? '';
        $errorCode   = is_string($rawCode) ? $rawCode : '';
        $description = $errData['error_description'] ?? '';
        $detail      = is_string($description) ? $description : '';
      } catch (Throwable) {
      }

      $status = $e->getResponse()->getStatusCode();
      $suffix = $this->formatDetail($errorCode, $detail, $status);

      if ($errorCode === 'invalid_grant') {
        // The same error code means opposite things per grant type, and
        // telling the operator to re-login when the *code* just expired
        // sends them in circles.
        if ($isCodeExchange) {
          $message = 'Código de autorização inválido, expirado ou já utilizado. Rode "ca auth login" e conclua '
          . 'o login no navegador sem reaproveitar URLs antigas.' . $this->endpointMismatchHint();
        } else {
          $message = 'Refresh token inválido ou expirado. Execute: ca auth login';
        }

        throw new CliException(ErrorKind::AuthFailed, false, $message . $suffix, $status, previous: $e);
      }

      throw new CliException(
          ErrorKind::AuthFailed,
          false,
          sprintf('Falha na autenticação (HTTP %d). Execute: ca auth login', $status) . $suffix,
          $status,
          previous: $e,
      );
    } catch (Throwable $e) {
      throw new CliException(
          ErrorKind::Transient,
          true,
          'Erro de rede ao autenticar: ' . $e->getMessage(),
          previous: $e,
      );
    }

    return TokenData::fromOAuthResponse($data);
  }

  /**
   * A code is only redeemable at the server that minted it, and pointing the
   * two endpoints at different hosts fails exactly like an expired code — the
   * login itself looks perfectly healthy. That cost us a long debugging
   * session, so when the hosts disagree the error says so outright.
   */
  private function endpointMismatchHint(): string {
    $authorizeHost = parse_url($this->config->authorizeUrl, PHP_URL_HOST);
    $tokenHost     = parse_url($this->config->tokenUrl, PHP_URL_HOST);

    if (! is_string($authorizeHost) || ! is_string($tokenHost) || $authorizeHost === $tokenHost) {
      return '';
    }

    return sprintf(' Atenção: a autorização acontece em %s mas a troca do código em %s.', $authorizeHost, $tokenHost)
    . ' Um código só é resgatável em quem o emitiu — confira CA_AUTHORIZE_URL e CA_TOKEN_URL.';
  }

  /**
   * The provider's own error/error_description is the only thing that
   * distinguishes an expired code from a redirect_uri mismatch, so it is
   * carried into the envelope instead of being swallowed.
   */
  private function formatDetail(string $errorCode, string $detail, int $status): string {
    $parts = array_filter([$errorCode, $detail]);
    if ($parts === []) {
      return sprintf(' (HTTP %d, sem detalhe do provedor)', $status);
    }

    return ' Provedor respondeu: ' . implode(' — ', $parts);
  }
}
