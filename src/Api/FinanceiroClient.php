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
  // Cobranças
  // -------------------------------------------------------------------------

  /**
   * Gera uma cobrança (boleto, PIX ou link de pagamento) para a parcela de
   * uma conta a receber. Escrita síncrona — a resposta já traz a cobrança
   * criada, sem protocolo.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function gerarCobranca(array $payload): array {
    return $this->support->request(
        'POST',
        '/v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca',
        ['json' => $payload],
    );
  }

  /** @return array<mixed> */
  public function getCobranca(string $id): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/' . rawurlencode($id),
    );
  }

  /**
   * Cancela uma cobrança gerada incorretamente ou que precisa ser invalidada
   * antes do pagamento. A documentação lista resposta `200 OK` sem schema de
   * corpo (não `204`, diferente dos demais deletes do CLI), e é isso mesmo
   * que a API faz: `200` com corpo **vazio**, confirmado contra a produção em
   * 2026-08-19. Foi essa combinação que estourava o parser do CLI.
   *
   * @return array<mixed>
   */
  public function deleteCobranca(string $id): array {
    return $this->support->request(
        'DELETE',
        '/v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/' . rawurlencode($id),
    );
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
   * Atualiza parcialmente a parcela — nota, descrição, vencimento,
   * `composicao_valor`, método de pagamento, perda, `nsu` e conta financeira.
   *
   * **Não dá baixa.** O endpoint foi modelado aqui como se quitasse a
   * parcela; exercitado em 2026-08-19, ele respondeu `200` sem registrar
   * pagamento nenhum, porque `valor` e `data` não existem no schema e a API
   * descarta campo desconhecido em silêncio. Quem quita é `createBaixa()`.
   *
   * `versao` é obrigatório (controle otimista): sem ele a resposta é `409`,
   * não `400`. A escrita é **síncrona** e devolve a parcela atualizada.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function updateParcela(string $id, array $payload): array {
    return $this->support->request(
        'PATCH',
        '/v1/financeiro/eventos-financeiros/parcelas/' . rawurlencode($id),
        ['json' => $payload],
    );
  }

  /**
   * Endpoint não pagina: devolve o array completo de parcelas do evento.
   *
   * @return array<mixed>
   */
  public function listParcelasByEvento(string $idEvento): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/' . $idEvento . '/parcelas',
    );
  }

  // -------------------------------------------------------------------------
  // Baixas
  // -------------------------------------------------------------------------

  /**
   * Registra uma baixa (quitação) vinculada a uma parcela; a API atualiza o
   * status da parcela automaticamente. Recurso dedicado, mais rico que
   * `baixarParcela()` (data, valor, juros, multa, desconto, método de
   * pagamento); uma parcela pode ter mais de uma baixa (pagamento parcial).
   * Escrita síncrona — a resposta já traz a baixa criada, sem protocolo.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function createBaixa(string $idParcela, array $payload): array {
    return $this->support->request(
        'POST',
        '/v1/financeiro/eventos-financeiros/parcelas/' . rawurlencode($idParcela) . '/baixa',
        ['json' => $payload],
    );
  }

  /**
   * Endpoint não pagina: devolve o array completo de baixas da parcela.
   *
   * @return array<mixed>
   */
  public function listBaixasByParcela(string $idParcela): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/parcelas/' . rawurlencode($idParcela) . '/baixa',
    );
  }

  /** @return array<mixed> */
  public function getBaixa(string $id): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/parcelas/baixa/' . rawurlencode($id),
    );
  }

  /**
   * Atualização parcial otimista: a API exige o campo `versao` atual no
   * payload e o incrementa após o sucesso, para evitar conflito com
   * atualizações concorrentes.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function updateBaixa(string $id, array $payload): array {
    return $this->support->request(
        'PATCH',
        '/v1/financeiro/eventos-financeiros/parcelas/baixa/' . rawurlencode($id),
        ['json' => $payload],
    );
  }

  /**
   * A exclusão impacta diretamente o saldo e o histórico financeiro da
   * parcela associada. A documentação lista resposta `200 OK` sem schema de
   * corpo (não `204`, mesma observação de `deleteCobranca()`), confirmado
   * contra a produção em 2026-08-19: `200` com corpo vazio.
   *
   * @return array<mixed>
   */
  public function deleteBaixa(string $id): array {
    return $this->support->request(
        'DELETE',
        '/v1/financeiro/eventos-financeiros/parcelas/baixa/' . rawurlencode($id),
    );
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
  // Transferências
  // -------------------------------------------------------------------------

  /**
   * As datas vão em `YYYY-MM-DD` puro — diferente de `getAlteracoes`, que
   * exige o instante ISO 8601 completo no mesmo domínio `/financeiro/`.
   *
   * @return array<mixed>
   */
  public function listTransferencias(
      string $dataInicio,
      string $dataFim,
      int $pagina = 1,
      int $tamanhoPagina = 50,
  ): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/transferencias',
        [
          'query' => [
            'data_fim'       => $dataFim,
            'data_inicio'    => $dataInicio,
            'pagina'         => $pagina,
            'tamanho_pagina' => $tamanhoPagina,
          ],
        ],
    );
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

  /**
   * `sugestao_padrao` vai como string `'true'`/`'false'`: bool nativo vira
   * `1`/vazio via `http_build_query` e a API não reconhece esse formato.
   *
   * @return array<mixed>
   */
  public function getConfiguracaoPadraoCategorias(bool $sugestaoPadrao = true): array {
    return $this->support->request(
        'GET',
        '/v1/categorias/configuracao-padrao',
        [
          'query' => ['sugestao_padrao' => $sugestaoPadrao ? 'true' : 'false'],
        ],
    );
  }

  /** @return array<mixed> */
  public function listCategoriasDre(): array {
    return $this->support->request('GET', '/v1/financeiro/categorias-dre');
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

  /**
   * Escrita síncrona — a resposta já é o centro de custo criado, sem protocolo.
   *
   * @param array<string, mixed> $payload
   *
   * @return array<mixed>
   */
  public function createCentroDeCusto(array $payload): array {
    return $this->support->request('POST', '/v1/centro-de-custo', ['json' => $payload]);
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

  /**
   * Assim como `getAlteracoes`, as datas vão em ISO 8601 sem timezone
   * (fuso São Paulo/GMT-3 implícito, conforme a documentação).
   *
   * @return array<mixed>
   */
  public function listSaldoInicial(
      string $dataInicio,
      string $dataFim,
      int $pagina = 1,
      int $tamanhoPagina = 50,
  ): array {
    return $this->support->request(
        'GET',
        '/v1/financeiro/eventos-financeiros/saldo-inicial',
        [
          'query' => [
            'data_fim'       => $dataFim,
            'data_inicio'    => $dataInicio,
            'pagina'         => $pagina,
            'tamanho_pagina' => $tamanhoPagina,
          ],
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
