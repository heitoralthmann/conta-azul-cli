<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_merge;

/**
 * Paths conferidos contra a API real em 2026-08-15, não deduzidos da
 * documentação. Duas armadilhas que custaram caro e valem o aviso:
 *
 * - O segmento `/financeiro/` só existe em parte dos recursos. Categorias,
 *   centros de custo e contas financeiras ficam na raiz da v1.
 * - A nomenclatura não é uniforme: `categorias` no plural, mas
 *   `centro-de-custo` e `conta-financeira` no singular.
 *
 * A lista autoritativa de operações está em
 * https://developers.contaazul.com/docs/financial-apis-openapi/v1
 */
final class FinanceiroClient
{
  use ApiClientOperations;

  /**
   * Builds a finance API client using the legacy application dependencies.
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

  // -------------------------------------------------------------------------
  // Contas a Receber
  // -------------------------------------------------------------------------

  /**
   * O intervalo de vencimento é exigido pela API: sem ele a resposta é 400.
   *
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listContasAReceber(
      string $dataVencimentoDe,
      string $dataVencimentoAte,
      int $pagina = 1,
      int $tamanhoPagina = 50,
      array $filters = [],
  ): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/contas-a-receber/buscar',
        [
          'query' => array_merge(
              [
                'data_vencimento_ate' => $dataVencimentoAte,
                'data_vencimento_de'  => $dataVencimentoDe,
                'pagina'              => $pagina,
                'tamanho_pagina'      => $tamanhoPagina,
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
  public function createContaAReceber(array $payload, int $pollTimeout = 60, bool $noWait = false): array {
    $response = $this->support->request(
        'POST',
        '/v1/financeiro/eventos-financeiros/contas-a-receber',
        ['json' => $payload],
    );

    return $this->support->handleAsyncResponse($response, $pollTimeout, $noWait);
  }

  // -------------------------------------------------------------------------
  // Contas a Pagar
  // -------------------------------------------------------------------------

  /**
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listContasAPagar(
      string $dataVencimentoDe,
      string $dataVencimentoAte,
      int $pagina = 1,
      int $tamanhoPagina = 50,
      array $filters = [],
  ): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/contas-a-pagar/buscar',
        [
          'query' => array_merge(
              [
                'data_vencimento_ate' => $dataVencimentoAte,
                'data_vencimento_de'  => $dataVencimentoDe,
                'pagina'              => $pagina,
                'tamanho_pagina'      => $tamanhoPagina,
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
  public function createContaAPagar(array $payload, int $pollTimeout = 60, bool $noWait = false): array {
    $response = $this->support->request(
        'POST',
        '/v1/financeiro/eventos-financeiros/contas-a-pagar',
        ['json' => $payload],
    );

    return $this->support->handleAsyncResponse($response, $pollTimeout, $noWait);
  }

  // -------------------------------------------------------------------------
  // Parcelas
  // -------------------------------------------------------------------------

  /** @return array<mixed> */
  public function getParcela(string $id): array {
    return $this->support->request('GET', '/v1/financeiro/eventos-financeiros/parcelas/' . $id);
  }

  /**
   * A baixa é um PATCH na própria parcela — não existe subrecurso `/baixar`.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function baixarParcela(string $id, array $payload, int $pollTimeout = 60, bool $noWait = false): array {
    $response = $this->support->request(
        'PATCH',
        '/v1/financeiro/eventos-financeiros/parcelas/' . $id,
        ['json' => $payload],
    );

    return $this->support->handleAsyncResponse($response, $pollTimeout, $noWait);
  }

  // -------------------------------------------------------------------------
  // Contas Financeiras
  // -------------------------------------------------------------------------

  /** @return array<mixed> */
  public function listContasFinanceiras(int $pagina = 1, int $tamanhoPagina = 50): array {
    return $this->support->request(
        'GET',
        '/v1/conta-financeira',
        [
          'query' => ['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina],
        ],
    );
  }

  /** @return array<mixed> */
  public function getSaldoContaFinanceira(string $id): array {
    return $this->support->request('GET', '/v1/conta-financeira/' . $id . '/saldo-atual');
  }

  // -------------------------------------------------------------------------
  // Categorias
  // -------------------------------------------------------------------------

  /** @return array<mixed> */
  public function listCategorias(int $pagina = 1, int $tamanhoPagina = 50): array {
    return $this->support->request(
        'GET',
        '/v1/categorias',
        [
          'query' => ['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina],
        ],
    );
  }

  // -------------------------------------------------------------------------
  // Centros de Custo
  // -------------------------------------------------------------------------

  /** @return array<mixed> */
  public function listCentrosDeCusto(int $pagina = 1, int $tamanhoPagina = 50): array {
    return $this->support->request(
        'GET',
        '/v1/centro-de-custo',
        [
          'query' => ['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina],
        ],
    );
  }

  // -------------------------------------------------------------------------
  // Eventos Financeiros / Alterações
  // -------------------------------------------------------------------------

  /**
   * As datas vão em ISO 8601 **sem timezone** (`2026-08-01T00:00:00`). Com
   * sufixo `Z` ou offset a API responde 400.
   *
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function getAlteracoes(string $dataInicio, string $dataFim, array $filters = []): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/alteracoes',
        [
          'query' => array_merge(
              [
                'data_fim'    => $dataFim,
                'data_inicio' => $dataInicio,
              ],
              $filters,
          ),
        ],
    );
  }

  // -------------------------------------------------------------------------
  // Protocolo
  // -------------------------------------------------------------------------

  /** @return array<mixed> */
  public function getProtocolo(string $id): array {
    return $this->support->request('GET', '/v1/protocolo/' . $id);
  }
}
