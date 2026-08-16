<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_merge;
use function rawurlencode;

/** Cliente dos endpoints de serviços da API Conta Azul. */
final class ServicosClient
{
  use ApiClientOperations;

  /**
   * Builds a services API client using the legacy application dependencies.
   */
  public function __construct(
      Configuration $config,
      AuthManager $authManager,
      Logger $logger,
      Redactor $redactor,
      HttpClientInterface $httpClient,
  ) {
    $this->support = ApiClientSupport::fromLegacy($config, $authManager, $logger, $redactor, $httpClient);
  }

  /**
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listServicos(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
  {
    return $this->support->request(
        'GET',
        '/v1/servicos',
        [
          'query' => array_merge(
              [
                'pagina' => $pagina,
                'tamanho_pagina' => $tamanhoPagina,
              ],
              $filters,
          ),
        ],
    );
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function createServico(array $payload): array
  {
    return $this->support->request('POST', '/v1/servicos', ['json' => $payload]);
  }

  /** @return array<mixed> */
  public function getServico(string $id): array
  {
    return $this->support->request('GET', '/v1/servicos/' . rawurlencode($id));
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function updateServico(string $id, array $payload): array
  {
    return $this->support->request('PATCH', '/v1/servicos/' . rawurlencode($id), ['json' => $payload]);
  }

  /**
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function deleteServicos(array $payload): array
  {
    return $this->support->request('DELETE', '/v1/servicos', ['json' => $payload]);
  }
}
