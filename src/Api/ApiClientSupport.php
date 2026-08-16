<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Composes transport and protocol polling for a feature API client.
 *
 * The legacy factory keeps endpoint-client constructors stable while the
 * clients themselves depend on this small composition boundary instead of
 * inheriting request behavior from BaseClient.
 */
final class ApiClientSupport
{


    /**
     * @param ApiTransportInterface $transport Authenticated API transport.
     * @param ProtocolPoller $poller Asynchronous protocol resolver.
     */
    public function __construct(
        private readonly ApiTransportInterface $transport,
        private readonly ProtocolPoller $poller,
    ) {
    }


    /**
     * Builds the default support services from the existing application wiring.
     */
    public static function fromLegacy(
        Configuration $config,
        AuthManager $authManager,
        Logger $logger,
        Redactor $redactor,
        HttpClientInterface $httpClient,
    ): self {
        $sleeper = new NativeSleeper();
        $transport = new HttpApiTransport(
          $config,
          $authManager,
          $logger,
          $redactor,
          $httpClient,
          $sleeper,
          new RetryPolicy(),
        );

        return new self($transport, new ProtocolPoller($transport, $sleeper));
    }


    /**
     * Sends an authenticated request through the composed transport.
     *
     * @param array<string, mixed> $options
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $options=[]): array {
        return $this->transport->request($method, $path, $options);
    }


    /**
     * Returns an accepted response immediately or waits for its protocol.
     *
     * @param array<mixed> $response
     * @return array<mixed>
     */
    public function handleAsyncResponse(array $response, int $pollTimeout=60, bool $noWait=FALSE): array {
        $rawProtocolId = $response['protocolId'] ?? '';
        $protocolId    = is_string($rawProtocolId) ? $rawProtocolId : '';

        if ($protocolId === '' || $noWait) {
            return $response;
        }

        return $this->poller->poll($protocolId, $pollTimeout);
    }


}
