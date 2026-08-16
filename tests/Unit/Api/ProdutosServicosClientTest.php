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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProdutosServicosClientTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    private string $tokenPath = '';

    private const ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_API_BASE_URL', 'CA_CLI_TOKEN_PATH'];


    protected function setUp(): void {
        foreach (self::ENV_VARS as $var) {
            $this->originalEnv[$var] = getenv($var);
            putenv($var);
        }
        $this->tokenPath = sys_get_temp_dir() . '/ca-cli-produtos-' . uniqid() . '/tokens.json';
        putenv('CA_CLIENT_ID=id');
        putenv('CA_CLIENT_SECRET=secret');
        putenv('CA_API_BASE_URL=https://api-v2.contaazul.com');
        putenv("CA_CLI_TOKEN_PATH={$this->tokenPath}");

        (new TokenStore(new Configuration()))->save(
          new TokenData(
            accessToken: 'token',
            accessTokenExpiresAt: new \DateTimeImmutable('+30 minutes'),
            refreshToken: 'refresh-1',
            refreshTokenObtainedAt: new \DateTimeImmutable('-1 hour'),
          )
        );
    }


    protected function tearDown(): void {
        $dir = dirname($this->tokenPath);
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
        foreach ($this->originalEnv as $var => $value) {
            $value === FALSE ? putenv($var) : putenv("{$var}={$value}");
        }
    }


    /**
     * @return array<string, array{callable(ProdutosClient): mixed, string, string}>
     */
    public static function productEndpointProvider(): array {
        return [
            'lista produtos' => [fn (ProdutosClient $c) => $c->listProdutos(), 'GET', '/v1/produtos'],
            'cria produto' => [fn (ProdutosClient $c) => $c->createProduto(['nome' => 'Café']), 'POST', '/v1/produtos'],
            'busca produto' => [fn (ProdutosClient $c) => $c->getProduto('p-1'), 'GET', '/v1/produtos/p-1'],
            'atualiza produto' => [fn (ProdutosClient $c) => $c->updateProduto('p-1', ['nome' => 'Café']), 'PATCH', '/v1/produtos/p-1'],
            'exclui produto' => [fn (ProdutosClient $c) => $c->deleteProduto('p-1'), 'DELETE', '/v1/produtos/p-1'],
            'lista categorias' => [fn (ProdutosClient $c) => $c->listCategoriasProduto(), 'GET', '/v1/produtos/categorias'],
            'lista cest' => [fn (ProdutosClient $c) => $c->listCest(), 'GET', '/v1/produtos/cest'],
            'lista ncm' => [fn (ProdutosClient $c) => $c->listNcm(), 'GET', '/v1/produtos/ncm'],
            'lista unidades de medida' => [fn (ProdutosClient $c) => $c->listUnidadesMedida(), 'GET', '/v1/produtos/unidades-medida'],
            'lista categorias de ecommerce' => [fn (ProdutosClient $c) => $c->listCategoriasEcommerce(), 'GET', '/v1/produtos/ecommerce-categorias'],
            'lista marcas de ecommerce' => [fn (ProdutosClient $c) => $c->listMarcasEcommerce(), 'GET', '/v1/produtos/ecommerce-marcas'],
        ];
    }


    /**
     * @param callable(ProdutosClient): mixed $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('productEndpointProvider')]
    public function testProductEndpointsUseTheDocumentedPaths(callable $call, string $method, string $path): void {
        $captured = NULL;
        $call($this->productClientRecording($captured));

        self::assertNotNull($captured);
        self::assertSame($method, $captured['method']);
        self::assertSame('https://api-v2.contaazul.com' . $path, strtok($captured['url'], '?'));
    }


    /**
     * @return array<string, array{callable(ServicosClient): mixed, string, string}>
     */
    public static function serviceEndpointProvider(): array {
        return [
            'lista serviços' => [fn (ServicosClient $c) => $c->listServicos(), 'GET', '/v1/servicos'],
            'cria serviço' => [fn (ServicosClient $c) => $c->createServico(['nome' => 'Instalação']), 'POST', '/v1/servicos'],
            'busca serviço' => [fn (ServicosClient $c) => $c->getServico('s-1'), 'GET', '/v1/servicos/s-1'],
            'atualiza serviço' => [fn (ServicosClient $c) => $c->updateServico('s-1', ['nome' => 'Instalação']), 'PATCH', '/v1/servicos/s-1'],
            'exclui serviços em lote' => [fn (ServicosClient $c) => $c->deleteServicos(['ids' => ['s-1']]), 'DELETE', '/v1/servicos'],
        ];
    }


    /**
     * @param callable(ServicosClient): mixed $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('serviceEndpointProvider')]
    public function testServiceEndpointsUseTheDocumentedPaths(callable $call, string $method, string $path): void {
        $captured = NULL;
        $call($this->serviceClientRecording($captured));

        self::assertNotNull($captured);
        self::assertSame($method, $captured['method']);
        self::assertSame('https://api-v2.contaazul.com' . $path, strtok($captured['url'], '?'));
    }


    public function testProductListSendsPaginationAndFilters(): void {
        $captured = NULL;
        $client = $this->productClientRecording($captured);

        $client->listProdutos(2, 100, ['busca' => 'café', 'categoria_id' => 'cat-1']);

        self::assertNotNull($captured);
        parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
        self::assertSame('2', $query['pagina'] ?? NULL);
        self::assertSame('100', $query['tamanho_pagina'] ?? NULL);
        self::assertSame('café', $query['busca'] ?? NULL);
        self::assertSame('cat-1', $query['categoria_id'] ?? NULL);
    }


    public function testServiceBatchDeleteSendsJsonPayload(): void {
        $captured = NULL;
        $client = $this->serviceClientRecording($captured);

        $client->deleteServicos(['ids' => ['s-1', 's-2']]);

        self::assertNotNull($captured);
        self::assertSame(['ids' => ['s-1', 's-2']], json_decode((string) $captured['body'], TRUE));
    }


    /** @param array<string, mixed>|null $captured */
    private function productClientRecording(?array &$captured): ProdutosClient {
        return new ProdutosClient(...$this->dependencies($captured));
    }


    /** @param array<string, mixed>|null $captured */
    private function serviceClientRecording(?array &$captured): ServicosClient {
        return new ServicosClient(...$this->dependencies($captured));
    }


    /**
     * @param array<string, mixed>|null $captured
     * @return array{Configuration, AuthManager, Logger, Redactor, MockHttpClient}
     */
    private function dependencies(?array &$captured): array {
        $http = new MockHttpClient(
          function (string $method, string $url, array $options) use (&$captured) {
            $captured = [
                'method' => $method,
                'url' => $url,
                'body' => $options['body'] ?? NULL,
            ];

            return new MockResponse('{}', ['http_code' => 200]);
          }
        );

        $config = new Configuration();
        $redactor = new Redactor();
        $auth = new AuthManager(
          new TokenStore($config),
          new OAuthClient(new MockHttpClient([]), $config),
          $config,
        );

        return [$config, $auth, new Logger($redactor), $redactor, $http];
    }


}
