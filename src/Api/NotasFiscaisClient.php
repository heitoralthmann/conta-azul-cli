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
 * Cliente dos endpoints de notas fiscais da API Conta Azul.
 *
 * Paths e schemas conferidos direto na documentação renderizada
 * (https://developers.contaazul.com/open-api-docs/open-api-invoice/v1), já
 * que o portal bloqueia `WebFetch`/`curl`; ainda não exercitados contra a
 * API real. A API só suporta consulta (NFe de produto e NFS-e de serviço) e
 * vínculo a MDF-e — não há emissão.
 */
final class NotasFiscaisClient
{
  use ApiClientOperations;

  /**
   * Builds an invoices API client using the legacy application dependencies.
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
   * Retorna somente NFe com status EMITIDA e CORRIGIDA_SUCESSO.
   *
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listNotasFiscais(
      string $dataInicial,
      string $dataFinal,
      int $pagina = 1,
      int $tamanhoPagina = 10,
      array $filters = [],
  ): array {
    return $this->support->request(
        'GET',
        '/v1/notas-fiscais',
        [
          'query' => array_merge(
              [
                'data_final'     => $dataFinal,
                'data_inicial'   => $dataInicial,
                'pagina'         => $pagina,
                'tamanho_pagina' => $tamanhoPagina,
              ],
              $filters,
          ),
        ],
    );
  }

  /**
   * O intervalo entre `data_competencia_de` e `data_competencia_ate` é
   * limitado a 15 dias pela API.
   *
   * @param array<string, mixed> $filters
   *
   * @return array<mixed>
   */
  public function listNotasFiscaisServico(
      string $dataCompetenciaDe,
      string $dataCompetenciaAte,
      int $pagina = 1,
      int $tamanhoPagina = 10,
      array $filters = [],
  ): array {
    return $this->support->request(
        'GET',
        '/v1/notas-fiscais-servico',
        [
          'query' => array_merge(
              [
                'data_competencia_ate' => $dataCompetenciaAte,
                'data_competencia_de'  => $dataCompetenciaDe,
                'pagina'               => $pagina,
                'tamanho_pagina'       => $tamanhoPagina,
              ],
              $filters,
          ),
        ],
    );
  }

  /**
   * Associa uma ou mais notas fiscais (por chave de acesso) a um MDF-e.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function vincularMdfe(array $payload): array {
    return $this->support->request('POST', '/v1/notas-fiscais/vinculo-mdfe', ['json' => $payload]);
  }

  /**
   * Busca uma nota fiscal de produto pela chave de acesso.
   *
   * A resposta é binária: o XML da NF-e ou, quando há carta de correção, um
   * ZIP contendo o XML da NF-e e o(s) XML(s) da(s) carta(s) de correção.
   *
   * @return array{content: string, contentType: string}
   */
  public function getNotaFiscalPorChave(string $chave): array {
    return $this->support->requestBinary('GET', '/v1/notas-fiscais/' . rawurlencode($chave));
  }
}
