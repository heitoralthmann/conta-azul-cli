<?php

declare(strict_types=1);

namespace ContaAzulCli\Bootstrap;

use ContaAzulCli\Api\ContratosClient;
use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Api\ServicosClient;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Command\Module\AuthCommandModule;
use ContaAzulCli\Command\Module\ContratoCommandModule;
use ContaAzulCli\Command\Module\FinanceiroCommandModule;
use ContaAzulCli\Command\Module\PessoaCommandModule;
use ContaAzulCli\Command\Module\ProdutoCommandModule;
use ContaAzulCli\Command\Module\ServicoCommandModule;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Config\EnvironmentConfigurationLoader;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\HttpClient\HttpClient;

/** Builds the application's external adapters, services, and command modules. */
final class ApplicationFactory
{
  private Logger|null $logger = null;

  /**
   * Assembles the complete console dependency graph.
   *
   * Configuration and adapter construction deliberately remain here: this
   * composition root is the only place that needs to know concrete classes.
   */
  public function build(): ApplicationComponents {
    $config       = $this->loadConfiguration();
    $redactor     = new Redactor();
    $this->logger = new Logger($redactor);
    $httpClient   = HttpClient::create();

    $errorEnvelope       = new ErrorEnvelope();
    $jsonRenderer        = new JsonRenderer();
    $paginationValidator = new PaginationValidator();
    $warningEnvelope     = new WarningEnvelope();
    $periodoPadrao       = new PeriodoPadrao();

    $tokenStore     = new TokenStore($config);
    $oauthClient    = new OAuthClient($httpClient, $config);
    $authManager    = new AuthManager($tokenStore, $oauthClient, $config);
    $callbackServer = new CallbackServer(
        timeoutSeconds: $config->callbackTimeout,
        certFile: $config->callbackCertFile,
        keyFile: $config->callbackKeyFile,
    );

    $financeiroClient = new FinanceiroClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $pessoasClient    = new PessoasClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $produtosClient   = new ProdutosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $servicosClient   = new ServicosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $contratosClient  = new ContratosClient($config, $authManager, $this->logger, $redactor, $httpClient);

    return new ApplicationComponents(
        $this->logger,
        [
          new AuthCommandModule($authManager, $callbackServer, $errorEnvelope),
          new FinanceiroCommandModule(
              $financeiroClient,
              $errorEnvelope,
              $jsonRenderer,
              $paginationValidator,
              $warningEnvelope,
              $periodoPadrao,
          ),
          new PessoaCommandModule($pessoasClient, $errorEnvelope, $jsonRenderer, $paginationValidator),
          new ProdutoCommandModule($produtosClient, $errorEnvelope, $jsonRenderer, $paginationValidator),
          new ServicoCommandModule($servicosClient, $errorEnvelope, $jsonRenderer, $paginationValidator),
          new ContratoCommandModule(
              $contratosClient,
              $errorEnvelope,
              $jsonRenderer,
              $paginationValidator,
              $warningEnvelope,
              $periodoPadrao,
          ),
        ],
    );
  }

  /** Returns the logger created before a later adapter fails to initialize. */
  public function logger(): Logger|null {
    return $this->logger;
  }

  /** Loads configuration through the dedicated environment boundary. */
  private function loadConfiguration(): Configuration {
    return (new EnvironmentConfigurationLoader())->load();
  }
}
