<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Auth;

use ContaAzulCli\Auth\TokenData;
use PHPUnit\Framework\TestCase;

final class TokenDataTest extends TestCase
{
    public function testFromOAuthResponseCalculatesExpiry(): void
    {
        $before = time();
        $token = TokenData::fromOAuthResponse([
            'access_token'  => 'at-123',
            'refresh_token' => 'rt-456',
            'expires_in'    => 3600,
            'token_type'    => 'Bearer',
        ]);
        $after = time();

        $expiresAt = $token->accessTokenExpiresAt->getTimestamp();
        self::assertGreaterThanOrEqual($before + 3600, $expiresAt);
        self::assertLessThanOrEqual($after + 3600, $expiresAt);
        self::assertSame('at-123', $token->accessToken);
        self::assertSame('rt-456', $token->refreshToken);
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $original = TokenData::fromOAuthResponse([
            'access_token'  => 'at-abc',
            'refresh_token' => 'rt-def',
            'expires_in'    => 7200,
            'token_type'    => 'Bearer',
        ]);

        $array = $original->toArray();
        $restored = TokenData::fromArray($array);

        self::assertSame($original->accessToken, $restored->accessToken);
        self::assertSame($original->refreshToken, $restored->refreshToken);
        self::assertSame(
            $original->accessTokenExpiresAt->getTimestamp(),
            $restored->accessTokenExpiresAt->getTimestamp(),
        );
    }

    public function testIsExpiringSoonReturnsTrueWhenLessThan60Seconds(): void
    {
        $expiry = new \DateTimeImmutable('+30 seconds', new \DateTimeZone('UTC'));
        $token = new TokenData(
            accessToken: 'at',
            accessTokenExpiresAt: $expiry,
            refreshToken: 'rt',
            refreshTokenObtainedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        self::assertTrue($token->isExpiringSoon());
    }

    public function testIsExpiringSoonReturnsFalseWhenMoreThan120Seconds(): void
    {
        $expiry = new \DateTimeImmutable('+300 seconds', new \DateTimeZone('UTC'));
        $token = new TokenData(
            accessToken: 'at',
            accessTokenExpiresAt: $expiry,
            refreshToken: 'rt',
            refreshTokenObtainedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        self::assertFalse($token->isExpiringSoon());
    }

    public function testFromArrayPreservesAllFields(): void
    {
        $data = [
            'access_token'              => 'my-at',
            'access_token_expires_at'   => '2026-06-01T12:00:00+00:00',
            'refresh_token'             => 'my-rt',
            'refresh_token_obtained_at' => '2026-05-01T12:00:00+00:00',
            'token_type'                => 'Bearer',
        ];

        $token = TokenData::fromArray($data);

        self::assertSame('my-at', $token->accessToken);
        self::assertSame('my-rt', $token->refreshToken);
        self::assertSame('Bearer', $token->tokenType);
    }
}
