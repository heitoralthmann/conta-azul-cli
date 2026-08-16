<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;

/** Resolves asynchronous Conta Azul protocol identifiers into final payloads. */
final class ProtocolPoller
{
    private const INITIAL_SLEEP = 1.0;
    private const MAX_SLEEP = 8.0;


    /**
     * @param ApiTransportInterface $transport Transport used for protocol GETs.
     * @param SleeperInterface $sleeper Delay implementation.
     */
    public function __construct(
        private readonly ApiTransportInterface $transport,
        private readonly SleeperInterface $sleeper,
    ) {
    }


    /**
     * Polls until success, terminal failure, or timeout.
     *
     * @return array<mixed>
     * @throws CliException with a known protocol id for resumable failures.
     */
    public function poll(string $protocolId, int $timeoutSeconds=60): array {
        $start        = time();
        $sleepSeconds = self::INITIAL_SLEEP;

        while (TRUE) {
            try {
                $data = $this->transport->request('GET', "/v1/protocolo/{$protocolId}");
            } catch (CliException $e) {
                if (in_array($e->kind, [ErrorKind::AuthFailed, ErrorKind::ClientError], TRUE)) {
                    throw $e;
                }

                throw new CliException(
                  ErrorKind::PollDropKnownId,
                  TRUE,
                  "Polling interrompido. protocol_id: {$protocolId}. Retome com: ca protocolo get {$protocolId}",
                  NULL,
                  $protocolId,
                  $this->transport->getCorrelationId(),
                  $e,
                );
            }

            $rawStatus = $data['status'] ?? '';
            $status    = is_string($rawStatus) ? $rawStatus : '';

            if ($status === 'SUCCESS') {
                /** @var array<mixed> $payload */
                $payload = is_array($data['data'] ?? NULL) ? $data['data'] : $data;

                return $payload;
            }

            if ($status === 'ERROR') {
                throw new CliException(
                  ErrorKind::ServerError,
                  FALSE,
                  "Operação falhou no servidor. protocol_id: {$protocolId}",
                  NULL,
                  $protocolId,
                  $this->transport->getCorrelationId(),
                );
            }

            if ((time() - $start) >= $timeoutSeconds) {
                throw new CliException(
                  ErrorKind::PollTimeoutKnownId,
                  FALSE,
                  "Timeout aguardando resultado. Consulte manualmente: ca protocolo get {$protocolId}",
                  NULL,
                  $protocolId,
                  $this->transport->getCorrelationId(),
                );
            }

            $this->sleeper->sleep($sleepSeconds);
            $sleepSeconds = min($sleepSeconds * 2.0, self::MAX_SLEEP);
        }
    }


}
