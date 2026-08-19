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
 * Os quatro endpoints foram exercitados contra a API de produção em
 * 2026-08-19, incluindo criação e exclusão em lote. Duas surpresas da API
 * estão documentadas em `COMMANDS.md` e não são compensadas aqui, porque o
 * cliente repassa o payload sem transformação: `observacoes` e
 * `observacoes_pagamento` trocam de lugar entre escrita e leitura, e
 * `total_itens` não conta os orçamentos em `ORCAMENTO_RECUSADO` que a
 * própria resposta devolve em `itens`.
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
