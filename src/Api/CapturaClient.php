<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function fopen;
use function implode;
use function rawurlencode;

/**
 * Cliente dos endpoints de captura de documentos (Developer Platform) da API Conta Azul.
 *
 * Paths e schemas conferidos direto no OpenAPI renderizado
 * (https://developers.contaazul.com/open-api-docs/developer-platform-open-api-capture/v1),
 * via Chrome (`_bundle/open-api-docs/developer-platform-open-api-capture.json`),
 * já que o portal bloqueia `WebFetch`/`curl`, e exercitados contra a API de
 * produção em 2026-08-19 — quando a codificação de `ids` que aquele OpenAPI
 * declara se mostrou errada (veja {@see self::statusDocumentos()}).
 */
final class CapturaClient
{
  use ApiClientOperations;

  /**
   * Builds a document capture API client using the legacy application dependencies.
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
   * Envia um arquivo (PDF, JPEG, PNG ou BMP; máximo de 10 MB) via
   * multipart/form-data para a extração automática de dados. O chamador é
   * responsável por validar que `$caminhoArquivo` existe e é legível.
   *
   * @return array<mixed>
   */
  public function enviarDocumento(string $caminhoArquivo, string|null $descricao = null): array {
    $body = ['arquivo' => fopen($caminhoArquivo, 'r')];
    if ($descricao !== null && $descricao !== '') {
      $body['descricao'] = $descricao;
    }

    return $this->support->request('POST', '/v1/captura/documentos', ['body' => $body]);
  }

  /**
   * Consulta o status de processamento de um ou mais documentos e das
   * capturas (extrações) geradas a partir deles. A API aceita até 20 ids
   * por chamada, cada um em uma repetição do parâmetro `ids`.
   *
   * O OpenAPI publicado declara `style: form, explode: false`, ou seja, os
   * ids separados por vírgula em um único parâmetro — e a API responde
   * `400` ("O valor informado para o campo 'ids' é inválido") a essa forma.
   * Ela quer o parâmetro **repetido** (`ids=a&ids=b`). Com um id só as duas
   * formas coincidem, e foi por isso que a divergência passou despercebida.
   *
   * A query vai montada na URL porque a opção `query` do Symfony indexa
   * arrays (`ids[0]=a&ids[1]=b`); os demais parâmetros seguem por ela, que
   * o cliente concatena ao que já estiver na URL.
   *
   * @param list<string> $ids
   *
   * @return array<mixed>
   */
  public function statusDocumentos(array $ids, int $pagina = 1, int $tamanhoPagina = 10): array {
    $idsQuery = implode(
        '&',
        array_map(static fn (string $id): string => 'ids=' . rawurlencode($id), $ids),
    );

    return $this->support->request(
        'GET',
        '/v1/captura/documentos/status?' . $idsQuery,
        [
          'query' => [
            'pagina'         => $pagina,
            'tamanho_pagina' => $tamanhoPagina,
          ],
        ],
    );
  }

  /**
   * Busca os dados extraídos de uma captura pelo id_captura obtido em
   * `statusDocumentos()`.
   *
   * @return array<mixed>
   */
  public function getCaptura(string $id): array {
    return $this->support->request('GET', '/v1/captura/' . rawurlencode($id));
  }

  /**
   * Confirma a prévia sugerida pela IA Captura e cria o evento financeiro
   * correspondente. A requisição não possui corpo.
   *
   * @return array<mixed>
   */
  public function aceitarCaptura(string $id): array {
    return $this->support->request('POST', '/v1/captura/' . rawurlencode($id));
  }

  /**
   * Recusa a prévia do evento financeiro sugerida pela captura. Resposta
   * `204 No Content`.
   *
   * @return array<mixed>
   */
  public function recusarCaptura(string $id): array {
    return $this->support->request('DELETE', '/v1/captura/' . rawurlencode($id));
  }
}
