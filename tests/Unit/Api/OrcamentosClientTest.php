<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\OrcamentosClient;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenData;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use DateTimeImmutable;
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

final class OrcamentosClientTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private string $tokenPath = '';

  private const array ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_API_BASE_URL', 'CA_CLI_TOKEN_PATH'];

  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-orcamentos-' . uniqid() . '/tokens.json';
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

  public function testListOrcamentosUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listOrcamentos();

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/orcamentos', strtok($captured['url'], '?'));
  }

  public function testListOrcamentosSendsPaginationAndOptionalFilters(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listOrcamentos(2, 20, [
      'campo_ordenado_ascendente' => 'NUMERO',
      'termo_busca'               => 'Orçamento 1',
    ]);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
    self::assertSame('Orçamento 1', $query['termo_busca'] ?? null);
    self::assertSame('NUMERO', $query['campo_ordenado_ascendente'] ?? null);
  }

  public function testCreateOrcamentoUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->createOrcamento(['id_cliente' => 'c-1', 'itens' => []]);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/orcamentos', strtok($captured['url'], '?'));
    self::assertSame(['id_cliente' => 'c-1', 'itens' => []], json_decode((string) $captured['body'], true));
  }

  public function testGetOrcamentoUsesTheDocumentedPathAndUrlEncodesTheId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getOrcamento('orcamento id/1');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/orcamentos/orcamento%20id%2F1',
        strtok($captured['url'], '?'),
    );
  }

  public function testExcluirOrcamentosEmLoteUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->excluirOrcamentosEmLote(['ids' => ['orcamento-1', 'orcamento-2']]);

    self::assertNotNull($captured);
    self::assertSame('DELETE', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/orcamentos', strtok($captured['url'], '?'));
    self::assertSame(['ids' => ['orcamento-1', 'orcamento-2']], json_decode((string) $captured['body'], true));
  }

  /**
   * A resposta de exclusão em lote é `204 No Content` — sem corpo para decodificar.
   */
  public function testExcluirOrcamentosEmLoteReturnsAnEmptyArrayFor204(): void {
    $client = $this->clientResponding(new MockResponse('', ['http_code' => 204]));

    self::assertSame([], $client->excluirOrcamentosEmLote(['ids' => ['orcamento-1']]));
  }

  /** @param array{method: string, url: string, body: string|null}|null $captured */
  private function clientRecording(array|null &$captured): OrcamentosClient {
    $http = new MockHttpClient(
        static function (string $method, string $url, array $options) use (&$captured) {
          $captured = [
            'method' => $method,
            'url'    => $url,
            'body'   => $options['body'] ?? null,
          ];

          return new MockResponse('{}', ['http_code' => 200]);
        },
    );

    return $this->buildClient($http);
  }

  private function clientResponding(MockResponse $response): OrcamentosClient {
    return $this->buildClient(new MockHttpClient($response));
  }

  private function buildClient(MockHttpClient $http): OrcamentosClient {
    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new OrcamentosClient($config, $auth, new Logger($redactor), $redactor, $http);
  }
}
