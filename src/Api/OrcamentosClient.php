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

/**
 * Cliente dos endpoints de orçamentos da API Conta Azul.
 *
 * Paths e schemas conferidos direto no OpenAPI renderizado
 * (https://developers.contaazul.com/docs/open-api-proposal), já que o
 * portal bloqueia `WebFetch`/`curl`; ainda não exercitados contra a API real.
 */
final class OrcamentosClient
{
  use ApiClientOperations;

  /**
   * Builds a budgets API client using the legacy application dependencies.
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
  public function listOrcamentos(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array {
    return $this->support->request(
        'GET',
        '/v1/orcamentos',
        [
          'query' => array_merge(
              [
                'pagina'         => $pagina,
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
  public function createOrcamento(array $payload): array {
    return $this->support->request('POST', '/v1/orcamentos', ['json' => $payload]);
  }

  /** @return array<mixed> */
  public function getOrcamento(string $id): array {
    return $this->support->request('GET', '/v1/orcamentos/' . rawurlencode($id));
  }

  /**
   * A API aceita de 1 a 10 uuids de orçamento por chamada. Resposta `204 No Content`.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function excluirOrcamentosEmLote(array $payload): array {
    return $this->support->request('DELETE', '/v1/orcamentos', ['json' => $payload]);
  }
}
