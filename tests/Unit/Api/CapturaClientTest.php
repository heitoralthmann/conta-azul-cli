<?php

declare(strict_types=1);

namespace ContaAzulCli\Tests\Unit\Api;

use ContaAzulCli\Api\CapturaClient;
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
use function file_put_contents;
use function getenv;
use function glob;
use function is_callable;
use function is_dir;
use function is_string;
use function parse_str;
use function parse_url;
use function putenv;
use function rmdir;
use function str_starts_with;
use function strlen;
use function strtok;
use function substr;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

use const PHP_URL_QUERY;

final class CapturaClientTest extends TestCase
{
  /** @var array<string, string|false> */
  private array $originalEnv = [];

  private string $tokenPath  = '';
  private string $uploadPath = '';

  private const array ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_API_BASE_URL', 'CA_CLI_TOKEN_PATH'];

  protected function setUp(): void {
    foreach (self::ENV_VARS as $var) {
      $this->originalEnv[$var] = getenv($var);
      putenv($var);
    }

    $this->tokenPath = sys_get_temp_dir() . '/ca-cli-captura-' . uniqid() . '/tokens.json';
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

    $this->uploadPath = sys_get_temp_dir() . '/ca-cli-captura-upload-' . uniqid() . '.pdf';
    file_put_contents($this->uploadPath, '%PDF-1.4 conteudo de teste');
  }

  protected function tearDown(): void {
    $dir = dirname($this->tokenPath);
    if (is_dir($dir)) {
      array_map('unlink', glob($dir . '/*') ?: []);
      rmdir($dir);
    }

    // Best-effort cleanup: the temp file may already be gone.
    // phpcs:ignore Generic.PHP.NoSilencedErrors
    @unlink($this->uploadPath);

    foreach ($this->originalEnv as $var => $value) {
      $value === false ? putenv($var) : putenv($var . '=' . $value);
    }
  }

  public function testEnviarDocumentoUsesTheDocumentedPathAndMultipartBody(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->enviarDocumento($this->uploadPath, 'Recibo de teste');

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame('https://api-v2.contaazul.com/v1/captura/documentos', strtok($captured['url'], '?'));

    $body = $captured['body'];
    self::assertIsString($body);
    self::assertStringContainsString('name="arquivo"', $body);
    self::assertStringContainsString('%PDF-1.4 conteudo de teste', $body);
    self::assertStringContainsString('name="descricao"', $body);
    self::assertStringContainsString('Recibo de teste', $body);

    $contentType = $this->headerValue($captured['headers'], 'Content-Type');
    self::assertNotNull($contentType);
    self::assertStringStartsWith('multipart/form-data; boundary=', $contentType);
  }

  public function testEnviarDocumentoOmitsDescricaoWhenNotGiven(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->enviarDocumento($this->uploadPath);

    self::assertNotNull($captured);
    $body = $captured['body'];
    self::assertIsString($body);
    self::assertStringNotContainsString('name="descricao"', $body);
  }

  /**
   * The published OpenAPI declares `style: form, explode: false` for `ids`,
   * i.e. comma-joined — and production answers `400` ("O valor informado
   * para o campo 'ids' é inválido") to that form. It wants the parameter
   * repeated. Verified against production on 2026-08-19.
   *
   * parse_str() cannot express this: it keeps only the last of the repeated
   * keys, so the query string is asserted directly.
   */
  public function testStatusDocumentosRepeatsTheIdsParameterInsteadOfJoiningWithComma(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->statusDocumentos(['doc-1', 'doc-2'], 2, 20);

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/captura/documentos/status',
        strtok($captured['url'], '?'),
    );

    $query = (string) parse_url($captured['url'], PHP_URL_QUERY);
    self::assertStringContainsString('ids=doc-1&ids=doc-2', $query);
    self::assertStringNotContainsString('doc-1,', $query);
    self::assertStringNotContainsString('doc-1%2C', $query);
    self::assertStringNotContainsString('ids[0]', $query);
    self::assertStringNotContainsString('ids%5B', $query);
  }

  public function testStatusDocumentosSendsPaginationAlongsideTheRepeatedIds(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->statusDocumentos(['doc-1', 'doc-2'], 2, 20);

    self::assertNotNull($captured);
    parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
    self::assertSame('2', $query['pagina'] ?? null);
    self::assertSame('20', $query['tamanho_pagina'] ?? null);
  }

  public function testStatusDocumentosUrlEncodesEachId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->statusDocumentos(['doc 1/a', 'doc-2']);

    self::assertNotNull($captured);
    self::assertStringContainsString(
        'ids=doc%201%2Fa&ids=doc-2',
        (string) parse_url($captured['url'], PHP_URL_QUERY),
    );
  }

  public function testStatusDocumentosStillWorksForASingleId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->statusDocumentos(['doc-1']);

    self::assertNotNull($captured);
    $query = (string) parse_url($captured['url'], PHP_URL_QUERY);
    self::assertStringContainsString('ids=doc-1', $query);
    self::assertStringNotContainsString('ids=doc-1&ids=', $query);
  }

  public function testGetCapturaUsesTheDocumentedPathAndUrlEncodesTheId(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->getCaptura('captura id/1');

    self::assertNotNull($captured);
    self::assertSame('GET', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/captura/captura%20id%2F1',
        strtok($captured['url'], '?'),
    );
  }

  public function testAceitarCapturaSendsAPostWithNoBody(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->aceitarCaptura('captura-1');

    self::assertNotNull($captured);
    self::assertSame('POST', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/captura/captura-1',
        strtok($captured['url'], '?'),
    );
    self::assertNull($captured['body']);
  }

  public function testRecusarCapturaUsesDelete(): void {
    $captured = null;
    $client   = $this->clientRecording($captured);

    $client->recusarCaptura('captura-1');

    self::assertNotNull($captured);
    self::assertSame('DELETE', $captured['method']);
    self::assertSame(
        'https://api-v2.contaazul.com/v1/captura/captura-1',
        strtok($captured['url'], '?'),
    );
  }

  /**
   * A resposta de recusa é `204 No Content` — sem corpo para decodificar.
   */
  public function testRecusarCapturaReturnsAnEmptyArrayFor204(): void {
    $client = $this->clientResponding(new MockResponse('', ['http_code' => 204]));

    self::assertSame([], $client->recusarCaptura('captura-1'));
  }

  /**
   * MockResponse::fromRequest() drains a multipart body Closure right after
   * the response factory returns (to simulate upload progress), so the body
   * must be materialized to a string here, before that drain happens.
   *
   * @param array{method: string, url: string, body: string|null, headers: list<string>}|null $captured
   */
  private function clientRecording(array|null &$captured): CapturaClient {
    $http = new MockHttpClient(
        function (string $method, string $url, array $options) use (&$captured) {
          $captured = [
            'method'  => $method,
            'url'     => $url,
            'body'    => $this->materializeBody($options['body'] ?? null),
            'headers' => $options['headers'] ?? [],
          ];

          return new MockResponse('{}', ['http_code' => 200]);
        },
    );

    return $this->buildClient($http);
  }

  private function clientResponding(MockResponse $response): CapturaClient {
    return $this->buildClient(new MockHttpClient($response));
  }

  private function buildClient(MockHttpClient $http): CapturaClient {
    $config   = new Configuration();
    $redactor = new Redactor();
    $auth     = new AuthManager(
        new TokenStore($config),
        new OAuthClient(new MockHttpClient([]), $config),
        $config,
    );

    return new CapturaClient($config, $auth, new Logger($redactor), $redactor, $http);
  }

  /** Symfony encodes a multipart body as a repeatable no-arg generator closure. */
  private function materializeBody(mixed $body): string|null {
    if ($body === null || is_string($body)) {
      return $body;
    }

    self::assertTrue(is_callable($body));

    $materialized = '';
    while (($chunk = $body()) !== '') {
      $materialized .= $chunk;
    }

    return $materialized;
  }

  /** @param list<string> $headers */
  private function headerValue(array $headers, string $name): string|null {
    foreach ($headers as $header) {
      if (str_starts_with($header, $name . ':')) {
        return trim(substr($header, strlen($name) + 1));
      }
    }

    return null;
  }
}
