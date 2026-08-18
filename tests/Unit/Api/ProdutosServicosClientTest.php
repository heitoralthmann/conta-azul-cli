<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Api\ServicosClient;
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

final class ProdutosServicosClientTest extends TestCase
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

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-produtos-' . uniqid() . '/tokens.json';
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

  /** @return array<string, array{callable(ProdutosClient): mixed, string, string}> */
  public static function productEndpointProvider(): array {
    return [
      'lista produtos'                => [static fn (ProdutosClient $c) => $c->listProdutos(), 'GET', '/v1/produtos'],
      'cria produto'                  => [
        static fn (ProdutosClient $c) => $c->createProduto(['nome' => 'Café']),
        'POST',
        '/v1/produtos',
      ],
      'busca produto'                 => [
        static fn (ProdutosClient $c) => $c->getProduto('p-1'),
        'GET',
        '/v1/produtos/p-1',
      ],
      'atualiza produto'              => [
        static fn (ProdutosClient $c) => $c->updateProduto('p-1', ['nome' => 'Café']),
        'PATCH',
        '/v1/produtos/p-1',
      ],
      'exclui produto'                => [
        static fn (ProdutosClient $c) => $c->deleteProduto('p-1'),
        'DELETE',
        '/v1/produtos/p-1',
      ],
      'lista categorias'              => [
        static fn (ProdutosClient $c) => $c->listCategoriasProduto(),
        'GET',
        '/v1/produtos/categorias',
      ],
      'lista cest'                    => [static fn (ProdutosClient $c) => $c->listCest(), 'GET', '/v1/produtos/cest'],
      'lista ncm'                     => [static fn (ProdutosClient $c) => $c->listNcm(), 'GET', '/v1/produtos/ncm'],
      'lista unidades de medida'      => [
        static fn (ProdutosClient $c) => $c->listUnidadesMedida(),
        'GET',
        '/v1/produtos/unidades-medida',
      ],
      'lista categorias de ecommerce' => [
        static fn (ProdutosClient $c) => $c->listCategoriasEcommerce(),
        'GET',
        '/v1/produtos/ecommerce-categorias',
      ],
      'lista marcas de ecommerce'     => [
        static fn (ProdutosClient $c) => $c->listMarcasEcommerce(),
        'GET',
        '/v1/produtos/ecommerce-marcas',
      ],
    ];
  }

  /** @param callable(ProdutosClient): mixed $call */
  #[DataProvider('productEndpointProvider')]
  public function testProductEndpointsUseTheDocumentedPaths(callable $call, string $method, string $path): void {
    $captured = null;
    $call($this->productClientRecording($captured));

    self::assertNotNull($captured);
    self::assertSame($method, $captured['method']);
    self::assertSame('https://api-v2.contaazul.com' . $path, strtok($captured['url'], '?'));
  }

  /** @return array<string, array{callable(ServicosClient): mixed, string, string}> */
  public static function serviceEndpointProvider(): array {
    return [
      'lista serviços'          => [static fn (ServicosClient $c) => $c->listServicos(), 'GET', '/v1/servicos'],
      'cria serviço'            => [
        static fn (ServicosClient $c) => $c->createServico(['nome' => 'Instalação']),
        'POST',
        '/v1/servicos',
      ],
      'busca serviço'           => [static fn (ServicosClient $c) => $c->getServico('s-1'), 'GET', '/v1/servicos/s-1'],
      'atualiza serviço'        => [
        static fn (ServicosClient $c) => $c->updateServico('s-1', ['nome' => 'Instalação']),
        'PATCH',
        '/v1/servicos/s-1',
      ],
      'exclui serviços em lote' => [
        static fn (ServicosClient $c) => $c->deleteServicos(['ids' => ['s-1']]),
        'DELETE',
        '/v1/servicos',
      ],
    ];
  }

  /** @param callable(ServicosClient): mixed $call */
  #[DataProvider('serviceEndpointProvider')]
  public function testServiceEndpointsUseTheDocumentedPaths(callable $call, string $method, string $path): void {
    $captured = null;
    $call($this->serviceClientRecording($captured));

    self::assertNotNull($captured);
    self::assertSame($method, $captured['method']);
    self::assertSame('https://api-v2.contaazul.com' . $path, strtok($captured['url'], '?'));
  }

  public function testProductListSendsPaginationAndFilters(): void {
    $captured = null;
    $client   = $this->productClientRecording($captured);

    $client->listProdutos(2, 100, ['busca' => 'café', 'categoria_id' => 'cat-1']);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('100', $query['tamanho_pagina'] ?? null);
    self::assertSame('café', $query['busca'] ?? null);
    self::assertSame('cat-1', $query['categoria_id'] ?? null);
  }

  public function testServiceBatchDeleteSendsJsonPayload(): void {
    $captured = null;
    $client   = $this->serviceClientRecording($captured);

    $client->deleteServicos(['ids' => ['s-1', 's-2']]);

    self::assertNotNull($captured);
    self::assertSame(['ids' => ['s-1', 's-2']], json_decode((string) $captured['body'], true));
  }

  /** @param array<string, mixed>|null $captured */
  private function productClientRecording(array|null &$captured): ProdutosClient {
    return new ProdutosClient(...$this->dependencies($captured));
  }

  /** @param array<string, mixed>|null $captured */
  private function serviceClientRecording(array|null &$captured): ServicosClient {
    return new ServicosClient(...$this->dependencies($captured));
  }

  /**
   * @param array<string, mixed>|null $captured
   *
   * @return array{Configuration, AuthManager, Logger, Redactor, MockHttpClient}
   */
  private function dependencies(array|null &$captured): array {
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

    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return [$config, $auth, new Logger($redactor), $redactor, $http];
  }
}
