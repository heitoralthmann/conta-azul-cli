<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function array_map;
use function dirname;
use function getenv;
use function glob;
use function is_dir;
use function json_decode;
use function parse_str;
use function parse_url;
use function putenv;
use function rmdir;
use function strtok;
use function sys_get_temp_dir;
use function uniqid;

use const PHP_URL_QUERY;

/**
 * Estes paths já estiveram todos errados: foram escritos a partir da
 * documentação e só em 2026-08-15, quando o bloqueio END_TRIAL da conta saiu,
 * apareceu que a API respondia 404 para praticamente tudo. Os valores aqui
 * foram conferidos contra a API real — o teste existe para que uma próxima
 * mudança não os quebre em silêncio de novo.
 */
final class FinanceiroClientTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private const array ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_API_BASE_URL', 'CA_CLI_TOKEN_PATH'];

  private string $tokenPath = '';

  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-fin-' . uniqid() . '/tokens.json';
    putenv('CA_CLIENT_ID=id');
    putenv('CA_CLIENT_SECRET=secret');
    putenv('CA_API_BASE_URL=https://api-v2.contaazul.com');
    putenv('CA_CLI_TOKEN_PATH=' . $this->tokenPath);

    (new TokenStore(new Configuration()))->save(
        new TokenData(
            accessToken: 'token',
            accessTokenExpiresAt: new DateTimeImmutable('+30 minutes'),
            refreshToken: 'refresh-1',
            refreshTokenObtainedAt: new DateTimeImmutable('-1 hour'),
        ),
    );
  }

  protected function tearDown(): void {
    $dir = dirname($this->tokenPath);
    if (is_dir($dir)) {
      array_map('unlink', glob($dir . '/*') ?: []);
      rmdir($dir);
    }

    foreach ($this->originalEnv as $var => $value) {
      $value === false ? putenv($var) : putenv($var . '=' . $value);
    }
  }

  /** @return array<string, array{callable(FinanceiroClient): mixed, string, string}> */
  public static function endpointProvider(): array {
    return [
      'alteracoes fica sob eventos-financeiros'               => [
        static fn (FinanceiroClient $c) => $c->getAlteracoes('2026-08-01T00:00:00', '2026-08-31T23:59:59'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/alteracoes',
      ],
      'categorias DRE ficam sob financeiro, no plural'        => [
        static fn (FinanceiroClient $c) => $c->listCategoriasDre(),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/categorias-dre',
      ],
      'categorias fica na raiz da v1, no plural'              => [
        static fn (FinanceiroClient $c) => $c->listCategorias(),
        'GET',
        'https://api-v2.contaazul.com/v1/categorias',
      ],
      'centro de custo fica na raiz da v1, no singular'       => [
        static fn (FinanceiroClient $c) => $c->listCentrosDeCusto(),
        'GET',
        'https://api-v2.contaazul.com/v1/centro-de-custo',
      ],
      'configuração padrão de categorias'                     => [
        static fn (FinanceiroClient $c) => $c->getConfiguracaoPadraoCategorias(),
        'GET',
        'https://api-v2.contaazul.com/v1/categorias/configuracao-padrao',
      ],
      'conta financeira fica na raiz da v1, no singular'      => [
        static fn (FinanceiroClient $c) => $c->listContasFinanceiras(),
        'GET',
        'https://api-v2.contaazul.com/v1/conta-financeira',
      ],
      'contas a pagar são buscadas sob eventos-financeiros'   => [
        static fn (FinanceiroClient $c) => $c->listContasAPagar('2026-08-01', '2026-08-31'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-pagar/buscar',
      ],
      'contas a receber são buscadas sob eventos-financeiros' => [
        static fn (FinanceiroClient $c) => $c->listContasAReceber('2026-08-01', '2026-08-31'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-receber/buscar',
      ],
      'parcela fica sob eventos-financeiros'                  => [
        static fn (FinanceiroClient $c) => $c->getParcela('p1'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/parcelas/p1',
      ],
      'saldo inicial fica sob eventos-financeiros'            => [
        static fn (FinanceiroClient $c) => $c->listSaldoInicial('2026-08-01T00:00:00', '2026-08-31T23:59:59'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/saldo-inicial',
      ],
      'saldo é saldo-atual, não saldo'                        => [
        static fn (FinanceiroClient $c) => $c->getSaldoContaFinanceira('abc'),
        'GET',
        'https://api-v2.contaazul.com/v1/conta-financeira/abc/saldo-atual',
      ],
      'transferências ficam sob financeiro, no plural'        => [
        static fn (FinanceiroClient $c) => $c->listTransferencias('2026-08-01', '2026-08-31'),
        'GET',
        'https://api-v2.contaazul.com/v1/financeiro/transferencias',
      ],
    ];
  }

  /** @param callable(FinanceiroClient): mixed $call */
  #[DataProvider('endpointProvider')]
  public function testEndpointPathsMatchTheRealApi(callable $call, string $method, string $expectedUrl): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $call($client);

    self::assertNotNull($captured);
    self::assertSame($method, $captured['method']);
    self::assertSame($expectedUrl, strtok($captured['url'], '?'));
  }

  /** A baixa é um PATCH na parcela; o subrecurso /baixar nunca existiu. */
  public function testBaixaIsAPatchOnTheInstallmentItself(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->baixarParcela('p9', ['valor' => 10.0], noWait: true);

    self::assertNotNull($captured);
    self::assertSame('PATCH', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/parcelas/p9',
        $captured['url'],
    );
  }

  public function testSearchSendsTheRequiredDueDateRange(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listContasAReceber('2026-03-01', '2026-03-31');

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-03-01', $query['data_vencimento_de'] ?? null);
    self::assertSame('2026-03-31', $query['data_vencimento_ate'] ?? null);
  }

  /**
   * Escrita síncrona — diferente de `createContaAPagar`/`createContaAReceber`,
   * a resposta já é o centro de custo criado, sem protocolo. Path e schema
   * conferidos direto no OpenAPI renderizado (o portal bloqueia
   * `WebFetch`/`curl`); ainda não exercitado contra a API real.
   */
  public function testCreateCentroDeCustoUsesTheDocumentedPathAndBody(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->createCentroDeCusto(['nome' => 'Contabilidade', 'codigo' => '1040']);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/centro-de-custo', strtok($captured['url'], '?'));
    self::assertSame(
        ['nome' => 'Contabilidade', 'codigo' => '1040'],
        json_decode((string) $captured['body'], true),
    );
  }

  /**
   * Path e schema conferidos direto no OpenAPI renderizado
   * (https://developers.contaazul.com/docs/charge-apis-openapi/v1), spec
   * próprio de Cobranças; ainda não exercitado contra a API real.
   */
  public function testGerarCobrancaUsesTheDocumentedPathAndBody(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $payload = [
      'conta_bancaria'   => 'conta-1',
      'data_vencimento'  => '2026-09-01',
      'descricao_fatura' => 'Fatura #1',
      'id_parcela'       => 'parcela-1',
      'tipo'             => 'BOLETO',
    ];
    $client->gerarCobranca($payload);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca',
        strtok($captured['url'], '?'),
    );
    self::assertSame($payload, json_decode((string) $captured['body'], true));
  }

  public function testGetCobrancaUsesTheDocumentedPathAndUrlEncodesTheId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getCobranca('cobranca id/1');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/cobranca%20id%2F1',
        strtok($captured['url'], '?'),
    );
  }

  public function testDeleteCobrancaUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->deleteCobranca('cobranca-1');

    self::assertNotNull($captured);
    self::assertSame('DELETE', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/cobranca-1',
        strtok($captured['url'], '?'),
    );
  }

  /** A API espera `'true'`/`'false'` literal, não o `1`/vazio do PHP nativo. */
  public function testSugestaoPadraoIsSentAsLiteralBooleanString(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getConfiguracaoPadraoCategorias(false);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('false', $query['sugestao_padrao'] ?? null);
  }

  /** A API recusa `desde`; os parâmetros são data_inicio e data_fim. */
  public function testAlteracoesSendsDataInicioAndDataFim(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getAlteracoes('2026-08-01T00:00:00', '2026-08-31T23:59:59');

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01T00:00:00', $query['data_inicio'] ?? null);
    self::assertSame('2026-08-31T23:59:59', $query['data_fim'] ?? null);
    self::assertArrayNotHasKey('desde', $query);
  }

  /** Assim como `getAlteracoes`, o instante ISO 8601 vai sem timezone. */
  public function testListSaldoInicialSendsDataInicioDataFimAndPagination(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listSaldoInicial('2026-08-01T00:00:00', '2026-08-31T23:59:59', 2, 25);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01T00:00:00', $query['data_inicio'] ?? null);
    self::assertSame('2026-08-31T23:59:59', $query['data_fim'] ?? null);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('25', $query['tamanho_pagina'] ?? null);
  }

  /**
   * Diferente de `getAlteracoes`, as datas de transferências vão em
   * `YYYY-MM-DD` puro — sem o instante ISO 8601 completo.
   */
  public function testListTransferenciasSendsPlainDatesAndPagination(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listTransferencias('2026-08-01', '2026-08-31', 2, 25);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01', $query['data_inicio'] ?? null);
    self::assertSame('2026-08-31', $query['data_fim'] ?? null);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('25', $query['tamanho_pagina'] ?? null);
  }

  /** @param array{method: string, url: string, body: string|null}|null $captured */
  private function clientRecording(array|null &$captured): FinanceiroClient {
    $http = new MockHttpClient(
        static function (string $method, string $url, array $options) use (&$captured) {
          $captured = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null];

          return new MockResponse('{}', ['http_code' => 200]);
        },
    );

    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new FinanceiroClient($config, $auth, new Logger($redactor), $redactor, $http);
  }
}
