<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\RetryPolicy;
use PHPUnit\Framework\TestCase;

/** Verifies that retry decisions remain safe for reads and writes. */
final class RetryPolicyTest extends TestCase
{
    /** A GET retries transient gateway failures before the attempt limit. */
    public function testGetRetriesTransientResponses(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->shouldRetryResponse('GET', 503, 1));
        self::assertFalse($policy->shouldRetryResponse('GET', 503, 3));
        self::assertTrue($policy->shouldRetryTransport('GET', 2));
    }

    /** Writes only retry a rate-limit response, never an ambiguous server error. */
    public function testWritesOnlyRetryRateLimits(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->shouldRetryResponse('POST', 429, 1));
        self::assertFalse($policy->shouldRetryResponse('POST', 500, 1));
        self::assertFalse($policy->shouldRetryTransport('POST', 1));
    }

    /** Retry-After takes precedence over the documented exponential schedule. */
    public function testRetryAfterOverridesBackoff(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(5.0, $policy->delay(1, 5.0));
        self::assertSame(2.0, $policy->delay(2));
    }
}
