<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\PessoasClient;
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

final class PessoasClientTest extends TestCase
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
    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-person-' . uniqid() . '/tokens.json';
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
   * @return array<string, array{callable(PessoasClient): mixed, string, string}>
   */
  public static function endpointProvider(): array {
    return [
      'lista pessoas' => [fn (PessoasClient $c) => $c->listPessoas(), 'GET', '/v1/pessoas'],
      'cria pessoa' => [fn (PessoasClient $c) => $c->createPessoa(['nome' => 'Maria']), 'POST', '/v1/pessoas'],
      'busca por id' => [fn (PessoasClient $c) => $c->getPessoa('p-1'), 'GET', '/v1/pessoas/p-1'],
      'atualiza integralmente' => [fn (PessoasClient $c) => $c->updatePessoa('p-1', ['nome' => 'Maria']), 'PUT', '/v1/pessoas/p-1'],
      'atualiza parcialmente' => [fn (PessoasClient $c) => $c->patchPessoa('p-1', ['email' => 'maria@example.test']), 'PATCH', '/v1/pessoas/p-1'],
      'busca por id legado' => [fn (PessoasClient $c) => $c->getPessoaLegado('123'), 'GET', '/v1/pessoas/legado/123'],
      'ativa em lote' => [fn (PessoasClient $c) => $c->activatePessoas(['uuids' => ['p-1']]), 'POST', '/v1/pessoas/ativar'],
      'inativa em lote' => [fn (PessoasClient $c) => $c->deactivatePessoas(['uuids' => ['p-1']]), 'POST', '/v1/pessoas/inativar'],
      'exclui em lote' => [fn (PessoasClient $c) => $c->deletePessoas(['uuids' => ['p-1']]), 'POST', '/v1/pessoas/excluir'],
      'conta conectada' => [fn (PessoasClient $c) => $c->getContaConectada(), 'GET', '/v1/pessoas/conta-conectada'],
    ];
  }


  /**
   * @param callable(PessoasClient): mixed $call
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('endpointProvider')]
  public function testAllPeopleEndpointsUseTheDocumentedPaths(callable $call, string $method, string $path): void {
    $captured = NULL;
    $client   = $this->clientRecording($captured);

    $call($client);

    self::assertNotNull($captured);
    self::assertSame($method, $captured['method']);
    self::assertSame('https://api-v2.contaazul.com' . $path, strtok($captured['url'], '?'));
  }


  public function testListSendsPaginationAndFilters(): void {
    $captured = NULL;
    $client   = $this->clientRecording($captured);

    $client->listPessoas(2, 100, ['busca' => 'Maria', 'tipo_perfil' => 'Cliente']);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? NULL);
    self::assertSame('100', $query['tamanho_pagina'] ?? NULL);
    self::assertSame('Maria', $query['busca'] ?? NULL);
    self::assertSame('Cliente', $query['tipo_perfil'] ?? NULL);
  }


  public function testWritesSendJsonPayload(): void {
    $captured = NULL;
    $client   = $this->clientRecording($captured);

    $client->patchPessoa('p-1', ['email' => 'maria@example.test']);

    self::assertNotNull($captured);
    self::assertSame(['email' => 'maria@example.test'], json_decode((string) $captured['body'], TRUE));
  }


  /** @param array<string, mixed>|null $captured */
  private function clientRecording(?array &$captured): PessoasClient {
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

    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
      new TokenStore($config),
      new OAuthClient(new MockHttpClient([]), $config),
      $config,
    );

    return new PessoasClient($config, $auth, new Logger($redactor), $redactor, $http);
  }


}
