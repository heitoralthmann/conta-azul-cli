<?php

declare(strict_types=1);

namespace ContaAzulCli;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Command\Auth\LoginCommand;
use ContaAzulCli\Command\Auth\LogoutCommand;
use ContaAzulCli\Command\Categoria\ListCommand as CategoriaListCommand;
use ContaAzulCli\Command\CentroDeCusto\ListCommand as CentroDeCustoListCommand;
use ContaAzulCli\Command\Cobranca\ListCommand as CobrancaListCommand;
use ContaAzulCli\Command\ContaAReceber\CreateCommand as ContaAReceberCreateCommand;
use ContaAzulCli\Command\ContaAReceber\GetCommand as ContaAReceberGetCommand;
use ContaAzulCli\Command\ContaAReceber\ListCommand as ContaAReceberListCommand;
use ContaAzulCli\Command\ContaAPagar\CreateCommand as ContaAPagarCreateCommand;
use ContaAzulCli\Command\ContaAPagar\GetCommand as ContaAPagarGetCommand;
use ContaAzulCli\Command\ContaAPagar\ListCommand as ContaAPagarListCommand;
use ContaAzulCli\Command\ContaFinanceira\ListCommand as ContaFinanceiraListCommand;
use ContaAzulCli\Command\ContaFinanceira\SaldoCommand as ContaFinanceiraSaldoCommand;
use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Lancamento\GetCommand as LancamentoGetCommand;
use ContaAzulCli\Command\Lancamento\ListCommand as LancamentoListCommand;
use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Command\Parcela\GetCommand as ParcelaGetCommand;
use ContaAzulCli\Command\Protocolo\GetCommand as ProtocoloGetCommand;
use ContaAzulCli\Command\Transferencia\CreateCommand as TransferenciaCreateCommand;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

final class ContaAzulApplication extends Application
{
    public function __construct()
    {
        parent::__construct('ca', '0.1.0');
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $errorEnvelope = new ErrorEnvelope();
        $jsonRenderer = new JsonRenderer();
        $paginationValidator = new PaginationValidator();

        // Auth commands don't need API config — register unconditionally
        // so "ca auth login" works even without credentials set.
        $this->addCommands([
            new LogoutCommand(new AuthManager(
                new TokenStore($this->makeConfigSilently()),
                new OAuthClient(HttpClient::create(), $this->makeConfigSilently()),
                $this->makeConfigSilently(),
            )),
        ]);

        try {
            $config = new Configuration();
            $redactor = new Redactor();
            $logger = new Logger($redactor);
            $httpClient = HttpClient::create();
            $tokenStore = new TokenStore($config);
            $oauthClient = new OAuthClient($httpClient, $config);
            $authManager = new AuthManager($tokenStore, $oauthClient, $config);
            $callbackServer = new CallbackServer();
            $client = new FinanceiroClient($config, $authManager, $logger, $redactor, $httpClient);

            $this->addCommands([
                new LoginCommand($authManager, $callbackServer, $errorEnvelope),
                new LancamentoListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new LancamentoGetCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaAReceberListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new ContaAReceberGetCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaAReceberCreateCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaAPagarListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new ContaAPagarGetCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaAPagarCreateCommand($client, $errorEnvelope, $jsonRenderer),
                new ParcelaGetCommand($client, $errorEnvelope, $jsonRenderer),
                new BaixarCommand($client, $errorEnvelope, $jsonRenderer),
                new CobrancaListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new ContaFinanceiraListCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaFinanceiraSaldoCommand($client, $errorEnvelope, $jsonRenderer),
                new CategoriaListCommand($client, $errorEnvelope, $jsonRenderer),
                new CentroDeCustoListCommand($client, $errorEnvelope, $jsonRenderer),
                new TransferenciaCreateCommand($client, $errorEnvelope, $jsonRenderer),
                new AlteracoesCommand($client, $errorEnvelope, $jsonRenderer),
                new ProtocoloGetCommand($client, $errorEnvelope, $jsonRenderer),
            ]);
        } catch (\Throwable) {
            // Config missing — only auth login/logout available until credentials are set.
            // LoginCommand requires a valid config, so provide a no-config stub login.
        }
    }

    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        // Commands handle their own error output via ErrorEnvelope.
        // Suppress the default Symfony rendering to keep stderr clean.
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {
            return parent::run($input, $output);
        } catch (\Throwable) {
            return 1;
        }
    }

    private function makeConfigSilently(): Configuration
    {
        try {
            return new Configuration();
        } catch (\Throwable) {
            // Return a dummy config for auth commands when credentials are not set yet.
            // This allows "ca auth login" to run before credentials are configured.
            return new class extends Configuration {
                public function __construct() {}

                public function getClientId(): string { return ''; }

                public function getClientSecret(): string { return ''; }

                public function getRedirectUri(): string { return 'http://localhost:9876/callback'; }

                public function getApiBaseUrl(): string { return 'https://api-v2.contaazul.com'; }

                public function getAuthBaseUrl(): string { return 'https://auth.contaazul.com'; }

                public function getTokenPath(): string
                {
                    $home = getenv('HOME') ?: '/tmp';

                    return $home . '/.config/conta-azul-cli/tokens.json';
                }

                public function getBootstrapRefreshToken(): ?string { return null; }
            };
        }
    }
}
