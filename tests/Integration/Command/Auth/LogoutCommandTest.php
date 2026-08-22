<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Integration\Command\Auth;

use ContaAzulCli\Auth\AccessTokenServiceInterface;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthGatewayInterface;
use ContaAzulCli\Auth\TokenRepositoryInterface;
use ContaAzulCli\Command\Auth\LogoutCommand;
use ContaAzulCli\Tests\Integration\Support\CommandTestCase;
use Symfony\Component\Console\Command\Command;

/**
 * LoginCommand is not covered here: CallbackServer is a real, final TCP
 * listener with no injectable transport, so driving it through CommandTester
 * would need a socket client synchronized against a blocking accept() loop —
 * disproportionate for a command-contract test. Its own behavior is already
 * unit-tested in tests/Unit/Auth/CallbackServerTest.php.
 */
final class LogoutCommandTest extends CommandTestCase
{
  public function testDeletesCredentialsAndAlwaysSucceeds(): void {
    $accessTokens = $this->createMock(AccessTokenServiceInterface::class);
    $accessTokens->expects(self::once())->method('logout');

    $authManager = new AuthManager(
        $this->createStub(TokenRepositoryInterface::class),
        $this->createStub(OAuthGatewayInterface::class),
        $this->testConfiguration(),
        $accessTokens,
    );

    $tester = $this->runCommand(new LogoutCommand($authManager));

    self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    self::assertStringContainsString('ca auth login', $tester->getDisplay());
  }
}
