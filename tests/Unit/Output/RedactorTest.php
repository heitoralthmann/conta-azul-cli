<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Output;

use ContaAzulCli\Output\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
  private Redactor $redactor;

  protected function setUp(): void {
    $this->redactor = new Redactor();
  }

  public function testRedactsAuthorizationKey(): void {
    $result = $this->redactor->redact(['authorization' => 'Bearer secret-token']);

    self::assertSame('[REDACTED]', $result['authorization']);
  }

  public function testRedactsAccessToken(): void {
    $result = $this->redactor->redact(['access_token' => 'my-access-token']);

    self::assertSame('[REDACTED]', $result['access_token']);
  }

  public function testRedactsRefreshToken(): void {
    $result = $this->redactor->redact(['refresh_token' => 'my-refresh-token']);

    self::assertSame('[REDACTED]', $result['refresh_token']);
  }

  public function testRedactsClientSecret(): void {
    $result = $this->redactor->redact(['client_secret' => 'super-secret']);

    self::assertSame('[REDACTED]', $result['client_secret']);
  }

  public function testPreservesNonSensitiveKeys(): void {
    $result = $this->redactor->redact(['username' => 'john', 'email' => 'john@example.com']);

    self::assertSame('john', $result['username']);
    self::assertSame('john@example.com', $result['email']);
  }

  public function testRedactsNestedArraysRecursively(): void {
    $result = $this->redactor->redact(
        [
          'user' => [
            'access_token' => 'secret',
            'name'         => 'John',
          ],
        ],
    );

    self::assertSame('John', $result['user']['name']);
    self::assertSame('[REDACTED]', $result['user']['access_token']);
  }

  public function testRedactStringReplacesInlineJsonValues(): void {
    $json   = '{"access_token":"my-secret-value","other":"keep"}';
    $result = $this->redactor->redactString($json);

    self::assertStringContainsString('[REDACTED]', $result);
    self::assertStringNotContainsString('my-secret-value', $result);
    self::assertStringContainsString('"other":"keep"', $result);
  }

  public function testIsCaseInsensitiveForKeys(): void {
    $result = $this->redactor->redact(['Authorization' => 'Bearer token']);

    self::assertSame('[REDACTED]', $result['Authorization']);
  }
}
