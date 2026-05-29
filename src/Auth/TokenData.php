<?php

declare(strict_types=1);

namespace ContaAzulCli\Auth;

final class TokenData
{
    public function __construct(
        public readonly string $accessToken,
        public readonly \DateTimeImmutable $accessTokenExpiresAt,
        public readonly string $refreshToken,
        public readonly \DateTimeImmutable $refreshTokenObtainedAt,
        public readonly string $tokenType = 'Bearer',
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: self::str($data['access_token'] ?? ''),
            accessTokenExpiresAt: new \DateTimeImmutable(self::str($data['access_token_expires_at'] ?? '')),
            refreshToken: self::str($data['refresh_token'] ?? ''),
            refreshTokenObtainedAt: new \DateTimeImmutable(self::str($data['refresh_token_obtained_at'] ?? '')),
            tokenType: self::str($data['token_type'] ?? 'Bearer'),
        );
    }

    /** @param array<string, mixed> $response */
    public static function fromOAuthResponse(array $response): self
    {
        $expiresIn = is_numeric($response['expires_in'] ?? null) ? (int) $response['expires_in'] : 3600;
        $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify("+{$expiresIn} seconds");

        return new self(
            accessToken: self::str($response['access_token'] ?? ''),
            accessTokenExpiresAt: $expiresAt,
            refreshToken: self::str($response['refresh_token'] ?? ''),
            refreshTokenObtainedAt: $now,
            tokenType: self::str($response['token_type'] ?? 'Bearer'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'access_token'              => $this->accessToken,
            'access_token_expires_at'   => $this->accessTokenExpiresAt->format(\DateTimeInterface::ATOM),
            'refresh_token'             => $this->refreshToken,
            'refresh_token_obtained_at' => $this->refreshTokenObtainedAt->format(\DateTimeInterface::ATOM),
            'token_type'                => $this->tokenType,
        ];
    }

    public function isExpiringSoon(int $thresholdSeconds = 60): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return ($this->accessTokenExpiresAt->getTimestamp() - $now->getTimestamp()) < $thresholdSeconds;
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
