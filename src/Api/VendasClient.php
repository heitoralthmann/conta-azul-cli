<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_merge;
use function is_int;
use function rawurlencode;

/**
 * Cliente dos endpoints de vendas da API Conta Azul.
 *
 * Os nove endpoints foram exercitados contra a API de produção em
 * 2026-08-19, incluindo o ciclo completo de escrita (`POST`, `PUT` e
 * `POST /exclusao-lote`). Paths, filtros e formatos de resposta conferem
 * com o que está em `COMMANDS.md`.
 */
final class VendasClient
{
  use ApiClientOperations;

  /**
   * Builds a sales API client using the legacy application dependencies.
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
  public function listVendas(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array {
    return $this->support->request(
        'GET',
        '/v1/venda/busca',
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
  public function createVenda(array $payload): array {
    return $this->support->request('POST', '/v1/venda', ['json' => $payload]);
  }

  /**
   * O id pode ser o uuid ou o id legado da venda.
   *
   * @return array<mixed>
   */
  public function getVenda(string $id): array {
    return $this->support->request('GET', '/v1/venda/' . rawurlencode($id));
  }

  /**
   * Escrita síncrona via PUT — a API não expõe PATCH para vendas.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function updateVenda(string $id, array $payload): array {
    return $this->support->request('PUT', '/v1/venda/' . rawurlencode($id), ['json' => $payload]);
  }

  /**
   * A resposta é um PDF binário, não JSON.
   *
   * @return array{content: string, contentType: string}
   */
  public function imprimirVenda(string $id): array {
    return $this->support->requestBinary('GET', '/v1/venda/' . rawurlencode($id) . '/imprimir');
  }

  /** @return array<mixed> */
  public function listItensVenda(string $idVenda, int $pagina = 1, int $tamanhoPagina = 10): array {
    return $this->support->request(
        'GET',
        '/v1/venda/' . rawurlencode($idVenda) . '/itens',
        [
          'query' => [
            'pagina'         => $pagina,
            'tamanho_pagina' => $tamanhoPagina,
          ],
        ],
    );
  }

  /**
   * Endpoint não pagina: devolve o array completo de vendedores cadastrados.
   *
   * @return array<mixed>
   */
  public function listVendedores(): array {
    return $this->support->request('GET', '/v1/venda/vendedores');
  }

  /**
   * O corpo da resposta é um inteiro solto (ou `null`), não um objeto.
   */
  public function getProximoNumeroVenda(): int|null {
    $numero = $this->support->requestScalar('GET', '/v1/venda/proximo-numero');

    return is_int($numero) ? $numero : null;
  }

  /**
   * A API aceita de 1 a 10 uuids de venda por chamada.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function excluirVendasEmLote(array $payload): array {
    return $this->support->request('POST', '/v1/venda/exclusao-lote', ['json' => $payload]);
  }
}
