<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\ContratosClient;
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

final class ContratosClientTest extends TestCase
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

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-contratos-' . uniqid() . '/tokens.json';
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

  public function testListUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listContratos('2026-08-15', '2027-08-15');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/contratos', strtok($captured['url'], '?'));
  }

  public function testCreateUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->createContrato(['id_cliente' => 'c-1']);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/contratos', strtok($captured['url'], '?'));
    self::assertSame(['id_cliente' => 'c-1'], json_decode((string) $captured['body'], true));
  }

  public function testProximoNumeroUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getProximoNumeroContrato();

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/contratos/proximo-numero', strtok($captured['url'], '?'));
  }

  public function testListSendsTheRequiredDateRangeAndPagination(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listContratos('2026-08-15', '2027-08-15', 2, 20);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-15', $query['data_inicio'] ?? null);
    self::assertSame('2027-08-15', $query['data_fim'] ?? null);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
  }

  public function testListSendsOptionalFilters(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listContratos('2026-08-15', '2027-08-15', filters: [
      'busca_textual'             => 'Contrato 1',
      'campo_ordenado_ascendente' => 'DATA_INICIO',
      'cliente_id'                => 'cli-1',
    ]);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('Contrato 1', $query['busca_textual'] ?? null);
    self::assertSame('cli-1', $query['cliente_id'] ?? null);
    self::assertSame('DATA_INICIO', $query['campo_ordenado_ascendente'] ?? null);
  }

  /**
   * O corpo da resposta é um inteiro solto (`4512645`), não um objeto — o
   * decode teria que ser especial para não estourar em `Response::toArray()`.
   */
  public function testProximoNumeroDecodesABareScalarBody(): void {
    $client = $this->clientResponding(new MockResponse('4512645', ['http_code' => 200]));

    self::assertSame(4512645, $client->getProximoNumeroContrato());
  }

  public function testProximoNumeroReturnsNullForABareNullBody(): void {
    $client = $this->clientResponding(new MockResponse('null', ['http_code' => 200]));

    self::assertNull($client->getProximoNumeroContrato());
  }

  /** @param array{method: string, url: string, body: string|null}|null $captured */
  private function clientRecording(array|null &$captured): ContratosClient {
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

  private function clientResponding(MockResponse $response): ContratosClient {
    return $this->buildClient(new MockHttpClient($response));
  }

  private function buildClient(MockHttpClient $http): ContratosClient {
    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new ContratosClient($config, $auth, new Logger($redactor), $redactor, $http);
  }
}
