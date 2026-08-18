<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\VendasClient;
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

final class VendasClientTest extends TestCase
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

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-vendas-' . uniqid() . '/tokens.json';
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

  public function testListVendasUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listVendas();

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/venda/busca', strtok($captured['url'], '?'));
  }

  public function testListVendasSendsPaginationAndOptionalFilters(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listVendas(2, 20, [
      'campo_ordenado_ascendente' => 'NUMERO',
      'termo_busca'               => 'Cliente 1',
    ]);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
    self::assertSame('Cliente 1', $query['termo_busca'] ?? null);
    self::assertSame('NUMERO', $query['campo_ordenado_ascendente'] ?? null);
  }

  public function testCreateVendaUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->createVenda(['id_cliente' => 'c-1', 'numero' => 1001]);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/venda', strtok($captured['url'], '?'));
    self::assertSame(['id_cliente' => 'c-1', 'numero' => 1001], json_decode((string) $captured['body'], true));
  }

  public function testGetVendaUsesTheDocumentedPathAndUrlEncodesTheId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getVenda('venda id/1');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/venda/venda%20id%2F1',
        strtok($captured['url'], '?'),
    );
  }

  public function testUpdateVendaSendsAPutRequestWithTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->updateVenda('venda-1', ['versao' => 1]);

    self::assertNotNull($captured);
    self::assertSame('PUT', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/venda/venda-1', strtok($captured['url'], '?'));
    self::assertSame(['versao' => 1], json_decode((string) $captured['body'], true));
  }

  public function testListItensVendaUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listItensVenda('venda-1', 2, 20);

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/venda/venda-1/itens', strtok($captured['url'], '?'));
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
  }

  public function testListVendedoresUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listVendedores();

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/venda/vendedores', strtok($captured['url'], '?'));
  }

  public function testExcluirVendasEmLoteUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->excluirVendasEmLote(['ids' => ['venda-1', 'venda-2']]);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/venda/exclusao-lote',
        strtok($captured['url'], '?'),
    );
    self::assertSame(['ids' => ['venda-1', 'venda-2']], json_decode((string) $captured['body'], true));
  }

  public function testProximoNumeroUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getProximoNumeroVenda();

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/venda/proximo-numero',
        strtok($captured['url'], '?'),
    );
  }

  /**
   * O corpo da resposta é um inteiro solto (`4512645`), não um objeto — o
   * decode teria que ser especial para não estourar em `Response::toArray()`.
   */
  public function testProximoNumeroDecodesABareScalarBody(): void {
    $client = $this->clientResponding(new MockResponse('4512645', ['http_code' => 200]));

    self::assertSame(4512645, $client->getProximoNumeroVenda());
  }

  public function testProximoNumeroReturnsNullForABareNullBody(): void {
    $client = $this->clientResponding(new MockResponse('null', ['http_code' => 200]));

    self::assertNull($client->getProximoNumeroVenda());
  }

  public function testImprimirVendaUsesTheDocumentedPathAndUrlEncodesTheId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->imprimirVenda('venda id/1');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/venda/venda%20id%2F1/imprimir',
        strtok($captured['url'], '?'),
    );
  }

  /**
   * O corpo é o PDF binário da venda, não JSON — o cliente precisa devolver
   * o conteúdo cru e o Content-Type, sem tentar decodificar como JSON.
   */
  public function testImprimirVendaReturnsTheRawBodyAndContentType(): void {
    $client = $this->clientResponding(
        new MockResponse(
            '%PDF-1.4 ...',
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/pdf']],
        ),
    );

    $resposta = $client->imprimirVenda('venda-1');

    self::assertSame('%PDF-1.4 ...', $resposta['content']);
    self::assertSame('application/pdf', $resposta['contentType']);
  }

  /** @param array{method: string, url: string, body: string|null}|null $captured */
  private function clientRecording(array|null &$captured): VendasClient {
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

  private function clientResponding(MockResponse $response): VendasClient {
    return $this->buildClient(new MockHttpClient($response));
  }

  private function buildClient(MockHttpClient $http): VendasClient {
    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new VendasClient($config, $auth, new Logger($redactor), $redactor, $http);
  }
}
