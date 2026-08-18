<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\NotasFiscaisClient;
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

final class NotasFiscaisClientTest extends TestCase
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

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-notas-fiscais-' . uniqid() . '/tokens.json';
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

  public function testListNotasFiscaisUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscais('2026-08-01', '2026-08-31');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/notas-fiscais', strtok($captured['url'], '?'));
  }

  public function testListNotasFiscaisSendsTheRequiredDateRangeAndPagination(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscais('2026-08-01', '2026-08-31', 2, 20);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01', $query['data_inicial'] ?? null);
    self::assertSame('2026-08-31', $query['data_final'] ?? null);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
  }

  public function testListNotasFiscaisSendsOptionalFilters(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscais('2026-08-01', '2026-08-31', filters: [
      'documento_tomador' => '12345678900',
      'id_venda'          => 'venda-1',
      'numero_nota'       => '1234',
    ]);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('12345678900', $query['documento_tomador'] ?? null);
    self::assertSame('1234', $query['numero_nota'] ?? null);
    self::assertSame('venda-1', $query['id_venda'] ?? null);
  }

  public function testListNotasFiscaisServicoUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscaisServico('2026-08-01', '2026-08-15');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/notas-fiscais-servico', strtok($captured['url'], '?'));
  }

  public function testListNotasFiscaisServicoSendsTheRequiredDateRangeAndPagination(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscaisServico('2026-08-01', '2026-08-15', 3, 50);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2026-08-01', $query['data_competencia_de'] ?? null);
    self::assertSame('2026-08-15', $query['data_competencia_ate'] ?? null);
    self::assertSame('3', $query['pagina'] ?? null);
    self::assertSame('50', $query['tamanho_pagina'] ?? null);
  }

  public function testListNotasFiscaisServicoSendsOptionalFilters(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->listNotasFiscaisServico('2026-08-01', '2026-08-15', filters: [
      'numero_venda'    => 1001,
      'status'          => ['EMITIDA', 'CANCELADA'],
      'tipo_negociacao' => 'VENDA',
    ]);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame(['EMITIDA', 'CANCELADA'], $query['status'] ?? null);
    self::assertSame('VENDA', $query['tipo_negociacao'] ?? null);
    self::assertSame('1001', $query['numero_venda'] ?? null);
  }

  public function testVincularMdfeUsesTheDocumentedPath(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->vincularMdfe([
      'chaves_acesso' => ['42250323643586000108550010000001151606401726'],
      'identificador' => '345345',
      'status'        => 'ENCERRADO',
    ]);

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/notas-fiscais/vinculo-mdfe',
        strtok($captured['url'], '?'),
    );
    self::assertSame(
        [
          'chaves_acesso' => ['42250323643586000108550010000001151606401726'],
          'identificador' => '345345',
          'status'        => 'ENCERRADO',
        ],
        json_decode((string) $captured['body'], true),
    );
  }

  public function testGetNotaFiscalPorChaveUsesTheDocumentedPathAndUrlEncodesTheKey(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getNotaFiscalPorChave('42250323643586000108550010000001151606401726');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/notas-fiscais/42250323643586000108550010000001151606401726',
        strtok($captured['url'], '?'),
    );
  }

  /**
   * O corpo é o XML binário da NF-e, não JSON — o cliente precisa devolver o
   * conteúdo cru e o Content-Type, sem tentar decodificar como JSON.
   */
  public function testGetNotaFiscalPorChaveReturnsTheRawBodyAndContentType(): void {
    $client = $this->clientResponding(
        new MockResponse(
            '<nfeProc>...</nfeProc>',
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/xml']],
        ),
    );

    $resposta = $client->getNotaFiscalPorChave('42250323643586000108550010000001151606401726');

    self::assertSame('<nfeProc>...</nfeProc>', $resposta['content']);
    self::assertSame('application/xml', $resposta['contentType']);
  }

  /** @param array{method: string, url: string, body: string|null}|null $captured */
  private function clientRecording(array|null &$captured): NotasFiscaisClient {
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

  private function clientResponding(MockResponse $response): NotasFiscaisClient {
    return $this->buildClient(new MockHttpClient($response));
  }

  private function buildClient(MockHttpClient $http): NotasFiscaisClient {
    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new NotasFiscaisClient($config, $auth, new Logger($redactor), $redactor, $http);
  }
}
