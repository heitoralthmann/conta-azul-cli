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

/** Cliente dos endpoints de contratos da API Conta Azul. */
final class ContratosClient
{
  use ApiClientOperations;

  /**
   * Builds a contracts API client using the legacy application dependencies.
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
   * `data_inicio`/`data_fim` são exigidos pela API.
   *
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listContratos(
      string $dataInicio,
      string $dataFim,
      int $pagina = 1,
      int $tamanhoPagina = 10,
      array $filters = [],
  ): array {
    return $this->support->request(
        'GET',
        '/v1/contratos',
        [
          'query' => array_merge(
              [
                'data_fim'       => $dataFim,
                'data_inicio'    => $dataInicio,
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
  public function createContrato(array $payload): array {
    return $this->support->request('POST', '/v1/contratos', ['json' => $payload]);
  }

  /**
   * O corpo da resposta é um inteiro solto (ou `null`), não um objeto.
   */
  public function getProximoNumeroContrato(): int|null {
    $numero = $this->support->requestScalar('GET', '/v1/contratos/proximo-numero');

    return is_int($numero) ? $numero : null;
  }

  /** @return array<mixed> */
  public function getContrato(string $id): array {
    return $this->support->request('GET', '/v1/contratos/' . rawurlencode($id));
  }

  /**
   * Exclusão permanente, cancelando as vendas associadas (agendadas e
   * efetivadas). Contratos em reajuste de valor não podem ser removidos.
   * Resposta `204 No Content`.
   *
   * @return array<mixed>
   */
  public function deleteContrato(string $id): array {
    return $this->support->request('DELETE', '/v1/contratos/' . rawurlencode($id));
  }

  /**
   * Desativa o contrato; ele deixa de gerar novas cobranças. Contratos em
   * reajuste de valor não podem ser encerrados. Sem corpo, resposta
   * `204 No Content`.
   *
   * @return array<mixed>
   */
  public function encerrarContrato(string $id): array {
    return $this->support->request('POST', '/v1/contratos/' . rawurlencode($id) . '/encerrar');
  }
}
