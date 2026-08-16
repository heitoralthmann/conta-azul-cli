<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

use function is_numeric;
use function is_string;

/**
 * Immutable OAuth credentials and their expiry metadata.
 *
 * @property-read string $accessToken OAuth access token.
 * @property-read DateTimeImmutable $accessTokenExpiresAt Access-token expiry.
 * @property-read string $refreshToken OAuth refresh token.
 * @property-read DateTimeImmutable $refreshTokenObtainedAt Refresh-token timestamp.
 * @property-read string $tokenType OAuth token type.
 */
final class TokenData
{
  /** Creates a normalized token record. */
  public function __construct(
      public readonly string $accessToken,
      public readonly DateTimeImmutable $accessTokenExpiresAt,
      public readonly string $refreshToken,
      public readonly DateTimeImmutable $refreshTokenObtainedAt,
      public readonly string $tokenType = 'Bearer',
  ) {
  }

  /**
   * Rehydrates a token record from the local persistence representation.
   *
   * @param array<string, mixed> $data
   */
  public static function fromArray(array $data): self
  {
    return new self(
        accessToken: self::str($data['access_token'] ?? ''),
        accessTokenExpiresAt: new DateTimeImmutable(self::str($data['access_token_expires_at'] ?? '')),
        refreshToken: self::str($data['refresh_token'] ?? ''),
        refreshTokenObtainedAt: new DateTimeImmutable(self::str($data['refresh_token_obtained_at'] ?? '')),
        tokenType: self::str($data['token_type'] ?? 'Bearer'),
    );
  }

  /**
   * Creates a token record from a provider OAuth response.
   *
   * @param array<string, mixed> $response
   */
  public static function fromOAuthResponse(array $response): self
  {
    $expiresIn = is_numeric($response['expires_in'] ?? null) ? (int) $response['expires_in'] : 3600;
    $now       = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $expiresAt = $now->modify('+' . $expiresIn . ' seconds');

    return new self(
        accessToken: self::str($response['access_token'] ?? ''),
        accessTokenExpiresAt: $expiresAt,
        refreshToken: self::str($response['refresh_token'] ?? ''),
        refreshTokenObtainedAt: $now,
        tokenType: self::str($response['token_type'] ?? 'Bearer'),
    );
  }

  /**
   * Serializes this token to the local persistence representation.
   *
   * @return array<string, string>
   */
  public function toArray(): array
  {
    return [
      'access_token'              => $this->accessToken,
      'access_token_expires_at'   => $this->accessTokenExpiresAt->format(DateTimeInterface::ATOM),
      'refresh_token'             => $this->refreshToken,
      'refresh_token_obtained_at' => $this->refreshTokenObtainedAt->format(DateTimeInterface::ATOM),
      'token_type'                => $this->tokenType,
    ];
  }

  /** Returns whether the access token expires within the safety threshold. */
  public function isExpiringSoon(int $thresholdSeconds = 60): bool
  {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return $this->accessTokenExpiresAt->getTimestamp() - $now->getTimestamp() < $thresholdSeconds;
  }

  /** Converts an untrusted persisted value to a string safely. */
  private static function str(mixed $value): string
  {
    return is_string($value) ? $value : '';
  }
}
