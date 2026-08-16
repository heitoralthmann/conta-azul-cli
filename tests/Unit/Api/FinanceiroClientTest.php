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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    private const ENV_VARS = ['CA_CLIENT_ID', 'CA_CLIENT_SECRET', 'CA_API_BASE_URL', 'CA_CLI_TOKEN_PATH'];

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
     * @return array<string, array{callable(FinanceiroClient): mixed, string, string}>
     */
    public static function endpointProvider(): array {
        return [
            'categorias fica na raiz da v1, no plural' => [
                fn (FinanceiroClient $c) => $c->listCategorias(),
                'GET',
                'https://api-v2.contaazul.com/v1/categorias',
            ],
            'centro de custo fica na raiz da v1, no singular' => [
                fn (FinanceiroClient $c) => $c->listCentrosDeCusto(),
                'GET',
                'https://api-v2.contaazul.com/v1/centro-de-custo',
            ],
            'conta financeira fica na raiz da v1, no singular' => [
                fn (FinanceiroClient $c) => $c->listContasFinanceiras(),
                'GET',
                'https://api-v2.contaazul.com/v1/conta-financeira',
            ],
            'saldo é saldo-atual, não saldo' => [
                fn (FinanceiroClient $c) => $c->getSaldoContaFinanceira('abc'),
                'GET',
                'https://api-v2.contaazul.com/v1/conta-financeira/abc/saldo-atual',
            ],
            'contas a receber são buscadas sob eventos-financeiros' => [
                fn (FinanceiroClient $c) => $c->listContasAReceber('2026-08-01', '2026-08-31'),
                'GET',
                'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-receber/buscar',
            ],
            'contas a pagar são buscadas sob eventos-financeiros' => [
                fn (FinanceiroClient $c) => $c->listContasAPagar('2026-08-01', '2026-08-31'),
                'GET',
                'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/contas-a-pagar/buscar',
            ],
            'parcela fica sob eventos-financeiros' => [
                fn (FinanceiroClient $c) => $c->getParcela('p1'),
                'GET',
                'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/parcelas/p1',
            ],
            'alteracoes fica sob eventos-financeiros' => [
                fn (FinanceiroClient $c) => $c->getAlteracoes('2026-08-01T00:00:00', '2026-08-31T23:59:59'),
                'GET',
                'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/alteracoes',
            ],
        ];
    }


    /**
     * @param callable(FinanceiroClient): mixed $call
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('endpointProvider')]
    public function testEndpointPathsMatchTheRealApi(callable $call, string $method, string $expectedUrl): void {
        $captured = NULL;
        $client   = $this->clientRecording($captured);

        $call($client);

        self::assertNotNull($captured);
        self::assertSame($method, $captured['method']);
        self::assertSame($expectedUrl, strtok($captured['url'], '?'));
    }


    /** A baixa é um PATCH na parcela; o subrecurso /baixar nunca existiu. */
    public function testBaixaIsAPatchOnTheInstallmentItself(): void {
        $captured = NULL;
        $client   = $this->clientRecording($captured);

        $client->baixarParcela('p9', ['valor' => 10.0], noWait: TRUE);

        self::assertNotNull($captured);
        self::assertSame('PATCH', $captured['method']);
        self::assertSame(
          'https://api-v2.contaazul.com/v1/financeiro/eventos-financeiros/parcelas/p9',
          $captured['url'],
        );
    }


    public function testSearchSendsTheRequiredDueDateRange(): void {
        $captured = NULL;
        $client   = $this->clientRecording($captured);

        $client->listContasAReceber('2026-03-01', '2026-03-31');

        self::assertNotNull($captured);
        parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
        self::assertSame('2026-03-01', $query['data_vencimento_de'] ?? NULL);
        self::assertSame('2026-03-31', $query['data_vencimento_ate'] ?? NULL);
    }


    /** A API recusa `desde`; os parâmetros são data_inicio e data_fim. */
    public function testAlteracoesSendsDataInicioAndDataFim(): void {
        $captured = NULL;
        $client   = $this->clientRecording($captured);

        $client->getAlteracoes('2026-08-01T00:00:00', '2026-08-31T23:59:59');

        self::assertNotNull($captured);
        parse_str((string) parse_url($captured['url'], PHP_URL_QUERY), $query);
        self::assertSame('2026-08-01T00:00:00', $query['data_inicio'] ?? NULL);
        self::assertSame('2026-08-31T23:59:59', $query['data_fim'] ?? NULL);
        self::assertArrayNotHasKey('desde', $query);
    }


    /** @param array{method: string, url: string}|null $captured */
    private function clientRecording(?array &$captured): FinanceiroClient {
        $http = new MockHttpClient(
          function (string $method, string $url) use (&$captured) {
            $captured = ['method' => $method, 'url' => $url];

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

        return new FinanceiroClient($config, $auth, new Logger($redactor), $redactor, $http);
    }


}
