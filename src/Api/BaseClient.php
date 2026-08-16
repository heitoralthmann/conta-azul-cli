<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Backwards-compatible façade shared by the feature API clients.
 *
 * Request execution and protocol polling are composed services. The façade
 * intentionally keeps the existing public methods while endpoint clients are
 * migrated to depend directly on ApiTransportInterface in a later step.
 */
class BaseClient
{
    private readonly ApiTransportInterface $transport;
    private readonly ProtocolPoller $poller;


    /**
     * Builds the default production transport and protocol poller.
     *
     * The callback sleeper preserves the protected sleep seam used by legacy
     * tests and by callers that need deterministic retry behavior.
     */
    public function __construct(
        Configuration $config,
        AuthManager $authManager,
        Logger $logger,
        Redactor $redactor,
        HttpClientInterface $httpClient,
    ) {
        $sleeper = new CallbackSleeper(
          function (float $seconds): void {
              $this->sleep($seconds);
          },
        );
        $this->transport = new HttpApiTransport(
          $config,
          $authManager,
          $logger,
          $redactor,
          $httpClient,
          $sleeper,
          new RetryPolicy(),
        );
        $this->poller = new ProtocolPoller($this->transport, $sleeper);
    }


    /**
     * Returns the correlation identifier shared by requests and poll errors.
     */
    public function getCorrelationId(): string {
        return $this->transport->getCorrelationId();
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
     * Resolves an asynchronous protocol identifier into its final payload.
     *
     * @return array<mixed>
     */
    public function pollProtocol(string $protocolId, int $timeoutSeconds=60): array {
        return $this->poller->poll($protocolId, $timeoutSeconds);
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

        return $this->pollProtocol($protocolId, $pollTimeout);
    }


    /**
     * Delays retries and polling. Tests override this method to record delays.
     */
    protected function sleep(float $seconds): void {
        (new NativeSleeper())->sleep($seconds);
    }


}
