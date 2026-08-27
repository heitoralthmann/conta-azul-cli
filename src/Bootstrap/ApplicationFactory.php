<?php

declare(strict_types=1);

namespace ContaAzulCli\Bootstrap;

use ContaAzulCli\Api\CapturaClient;
use ContaAzulCli\Api\ContratosClient;
use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\NotasFiscaisClient;
use ContaAzulCli\Api\OrcamentosClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Api\ServicosClient;
use ContaAzulCli\Api\VendasClient;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackCertificateProvisioner;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Command\Module\AuthCommandModule;
use ContaAzulCli\Command\Module\CapturaCommandModule;
use ContaAzulCli\Command\Module\ContratoCommandModule;
use ContaAzulCli\Command\Module\FinanceiroCommandModule;
use ContaAzulCli\Command\Module\NotaFiscalCommandModule;
use ContaAzulCli\Command\Module\OrcamentoCommandModule;
use ContaAzulCli\Command\Module\PessoaCommandModule;
use ContaAzulCli\Command\Module\ProdutoCommandModule;
use ContaAzulCli\Command\Module\ServicoCommandModule;
use ContaAzulCli\Command\Module\VendaCommandModule;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Config\ConfigException;
use ContaAzulCli\Config\ConfigFileLocator;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Config\EnvironmentConfigurationLoader;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\FormatterSelectorInterface;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\ResponseRenderer;
use ContaAzulCli\Output\WarningEnvelope;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

/** Builds the application's external adapters, services, and command modules. */
final class ApplicationFactory
{
  private Logger|null $logger = null;

  /**
   * Binds the factory to the formatter selector shared with the shell.
   *
   * The selector is owned by {@see \ContaAzulCli\ContaAzulApplication}, which
   * needs it before this factory runs so the always-available commands render
   * through the very same instance. Two selectors would mean `--format`
   * silently applied to only half the commands.
   */
  public function __construct(private readonly FormatterSelectorInterface $formatterSelector) {
  }

  /**
   * Assembles the complete console dependency graph.
   *
   * Configuration and adapter construction deliberately remain here: this
   * composition root is the only place that needs to know concrete classes.
   */
  public function build(): ApplicationComponents {
    $this->loadEnvironmentFile();

    $config       = $this->loadConfiguration();
    $redactor     = new Redactor();
    $this->logger = new Logger($redactor);
    $httpClient   = HttpClient::create();

    $errorEnvelope       = new ErrorEnvelope(null, $this->formatterSelector);
    $responseRenderer    = new ResponseRenderer(null, $this->formatterSelector);
    $paginationValidator = new PaginationValidator();
    $warningEnvelope     = new WarningEnvelope(null, $this->formatterSelector);
    $periodoPadrao       = new PeriodoPadrao();

    $tokenStore   = new TokenStore($config);
    $oauthClient  = new OAuthClient($httpClient, $config);
    $authManager  = new AuthManager($tokenStore, $oauthClient, $config);
    $certificates = new CallbackCertificateProvisioner();

    $financeiroClient   = new FinanceiroClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $pessoasClient      = new PessoasClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $produtosClient     = new ProdutosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $servicosClient     = new ServicosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $contratosClient    = new ContratosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $notasFiscaisClient = new NotasFiscaisClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $vendasClient       = new VendasClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $orcamentosClient   = new OrcamentosClient($config, $authManager, $this->logger, $redactor, $httpClient);
    $capturaClient      = new CapturaClient($config, $authManager, $this->logger, $redactor, $httpClient);

    return new ApplicationComponents(
        $this->logger,
        [
          new AuthCommandModule($authManager, $certificates, $config, $errorEnvelope),
          new FinanceiroCommandModule(
              $financeiroClient,
              $errorEnvelope,
              $responseRenderer,
              $paginationValidator,
              $warningEnvelope,
              $periodoPadrao,
          ),
          new PessoaCommandModule($pessoasClient, $errorEnvelope, $responseRenderer, $paginationValidator),
          new ProdutoCommandModule($produtosClient, $errorEnvelope, $responseRenderer, $paginationValidator),
          new ServicoCommandModule($servicosClient, $errorEnvelope, $responseRenderer, $paginationValidator),
          new ContratoCommandModule(
              $contratosClient,
              $errorEnvelope,
              $responseRenderer,
              $paginationValidator,
              $warningEnvelope,
              $periodoPadrao,
          ),
          new NotaFiscalCommandModule(
              $notasFiscaisClient,
              $errorEnvelope,
              $responseRenderer,
              $paginationValidator,
              $warningEnvelope,
              $periodoPadrao,
          ),
          new VendaCommandModule($vendasClient, $errorEnvelope, $responseRenderer, $paginationValidator),
          new OrcamentoCommandModule($orcamentosClient, $errorEnvelope, $responseRenderer, $paginationValidator),
          new CapturaCommandModule($capturaClient, $errorEnvelope, $responseRenderer, $paginationValidator),
        ],
    );
  }

  /** Returns the logger created before a later adapter fails to initialize. */
  public function logger(): Logger|null {
    return $this->logger;
  }

  /**
   * Loads the resolved `.env` file into the process environment.
   *
   * This runs here, and not in `bin/ca`, so a malformed or unreadable file
   * degrades to the same actionable error as a missing credential instead of
   * escaping as an uncaught fatal before any error envelope exists.
   *
   * @throws ConfigException When the explicit override cannot be read.
   */
  private function loadEnvironmentFile(): void {
    $path = ConfigFileLocator::forRuntime()->locate()->path();
    if ($path === null) {
      return;
    }

    $dotenv = new Dotenv();
    // usePutenv is load-bearing: the whole app reads configuration through
    // getenv(), which never sees $_ENV/$_SERVER on its own.
    $dotenv->usePutenv(true);
    // load(), never overload(): the real environment has to keep beating the
    // file. The documented precedence and the docs generator's stub
    // credentials both depend on it.
    $dotenv->load($path);
  }

  /** Loads configuration through the dedicated environment boundary. */
  private function loadConfiguration(): Configuration {
    return (new EnvironmentConfigurationLoader())->load();
  }
}
