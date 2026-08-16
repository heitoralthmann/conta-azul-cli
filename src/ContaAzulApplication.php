<?php

declare(strict_types=1);

namespace ContaAzulCli;

use ContaAzulCli\Api\FinanceiroClient;
use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Api\ProdutosClient;
use ContaAzulCli\Api\ServicosClient;
use ContaAzulCli\Auth\AuthManager;
use ContaAzulCli\Auth\CallbackServer;
use ContaAzulCli\Auth\OAuthClient;
use ContaAzulCli\Auth\TokenStore;
use ContaAzulCli\Command\Auth\LoginCommand;
use ContaAzulCli\Command\Auth\LogoutCommand;
use ContaAzulCli\Command\Categoria\ListCommand as CategoriaListCommand;
use ContaAzulCli\Command\CentroDeCusto\ListCommand as CentroDeCustoListCommand;
use ContaAzulCli\Command\ContaAReceber\CreateCommand as ContaAReceberCreateCommand;
use ContaAzulCli\Command\ContaAReceber\ListCommand as ContaAReceberListCommand;
use ContaAzulCli\Command\ContaAPagar\CreateCommand as ContaAPagarCreateCommand;
use ContaAzulCli\Command\ContaAPagar\ListCommand as ContaAPagarListCommand;
use ContaAzulCli\Command\ContaFinanceira\ListCommand as ContaFinanceiraListCommand;
use ContaAzulCli\Command\ContaFinanceira\SaldoCommand as ContaFinanceiraSaldoCommand;
use ContaAzulCli\Command\Financeiro\AlteracoesCommand;
use ContaAzulCli\Command\Support\PeriodoPadrao;
use ContaAzulCli\Command\Support\ResourceIdCommand;
use ContaAzulCli\Command\Support\ResourceIdJsonCommand;
use ContaAzulCli\Command\Support\ResourceJsonCommand;
use ContaAzulCli\Command\Support\ResourceListCommand;
use ContaAzulCli\Command\Parcela\BaixarCommand;
use ContaAzulCli\Command\Parcela\GetCommand as ParcelaGetCommand;
use ContaAzulCli\Command\Protocolo\GetCommand as ProtocoloGetCommand;
use ContaAzulCli\Command\Pessoa\BatchCommand as PessoaBatchCommand;
use ContaAzulCli\Command\Pessoa\ContaConectadaCommand as PessoaContaConectadaCommand;
use ContaAzulCli\Command\Pessoa\CreateCommand as PessoaCreateCommand;
use ContaAzulCli\Command\Pessoa\GetCommand as PessoaGetCommand;
use ContaAzulCli\Command\Pessoa\LegadoCommand as PessoaLegadoCommand;
use ContaAzulCli\Command\Pessoa\ListCommand as PessoaListCommand;
use ContaAzulCli\Command\Pessoa\PatchCommand as PessoaPatchCommand;
use ContaAzulCli\Command\Pessoa\UpdateCommand as PessoaUpdateCommand;
use ContaAzulCli\Config\Configuration;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
use ContaAzulCli\Output\WarningEnvelope;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

final class ContaAzulApplication extends Application
{
    private ?Logger $logger = NULL;
    private ?\Throwable $bootstrapError = NULL;


    public function __construct() {
        parent::__construct('ca', '0.1.0');
        $this->registerCommands();
    }


    private function registerCommands(): void {
        $errorEnvelope = new ErrorEnvelope();
        $jsonRenderer = new JsonRenderer();
        $paginationValidator = new PaginationValidator();
        $warningEnvelope = new WarningEnvelope();
        $periodoPadrao = new PeriodoPadrao();

        try {
            $config = new Configuration();
            $redactor = new Redactor();
            $logger = new Logger($redactor);
            $this->logger = $logger;
            $httpClient = HttpClient::create();
            $tokenStore = new TokenStore($config);
            $oauthClient = new OAuthClient($httpClient, $config);
            $authManager = new AuthManager($tokenStore, $oauthClient, $config);
            $callbackServer = new CallbackServer(
              timeoutSeconds: $config->getCallbackTimeout(),
              certFile: $config->getCallbackCertFile(),
              keyFile: $config->getCallbackKeyFile(),
            );
            $client = new FinanceiroClient($config, $authManager, $logger, $redactor, $httpClient);
            $pessoasClient = new PessoasClient($config, $authManager, $logger, $redactor, $httpClient);
            $produtosClient = new ProdutosClient($config, $authManager, $logger, $redactor, $httpClient);
            $servicosClient = new ServicosClient($config, $authManager, $logger, $redactor, $httpClient);

            $productFilters = [
                'busca' => 'busca',
                'codigo' => 'codigo',
                'ids' => 'ids',
                'status' => 'status',
                'categoria-id' => 'categoria_id',
            ];
            $serviceFilters = [
                'busca' => 'busca',
                'codigo' => 'codigo',
                'ids' => 'ids',
                'status' => 'status',
            ];

            $this->addCommands(
              [
                new LoginCommand($authManager, $callbackServer, $errorEnvelope),
                new LogoutCommand($authManager),
                new ContaAReceberListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator, $warningEnvelope, $periodoPadrao),
                new ContaAReceberCreateCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaAPagarListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator, $warningEnvelope, $periodoPadrao),
                new ContaAPagarCreateCommand($client, $errorEnvelope, $jsonRenderer),
                new ParcelaGetCommand($client, $errorEnvelope, $jsonRenderer),
                new BaixarCommand($client, $errorEnvelope, $jsonRenderer),
                new ContaFinanceiraListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new ContaFinanceiraSaldoCommand($client, $errorEnvelope, $jsonRenderer),
                new CategoriaListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new CentroDeCustoListCommand($client, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new AlteracoesCommand($client, $errorEnvelope, $jsonRenderer, $warningEnvelope, $periodoPadrao),
                new ProtocoloGetCommand($client, $errorEnvelope, $jsonRenderer),
                new PessoaListCommand($pessoasClient, $errorEnvelope, $jsonRenderer, $paginationValidator),
                new PessoaCreateCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new PessoaGetCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new PessoaUpdateCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new PessoaPatchCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new PessoaLegadoCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new PessoaBatchCommand($pessoasClient, $errorEnvelope, $jsonRenderer, 'pessoa ativar', 'activate'),
                new PessoaBatchCommand($pessoasClient, $errorEnvelope, $jsonRenderer, 'pessoa inativar', 'deactivate'),
                new PessoaBatchCommand($pessoasClient, $errorEnvelope, $jsonRenderer, 'pessoa excluir', 'delete'),
                new PessoaContaConectadaCommand($pessoasClient, $errorEnvelope, $jsonRenderer),
                new ResourceListCommand(
                  'produto list',
                  'Lista produtos por filtros',
                  $produtosClient->listProdutos(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  $productFilters,
                ),
                new ResourceJsonCommand(
                  'produto create',
                  'Cria um produto',
                  $produtosClient->createProduto(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'Payload JSON do produto',
                ),
                new ResourceIdCommand(
                  'produto get',
                  'Busca um produto por ID',
                  $produtosClient->getProduto(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'ID do produto',
                ),
                new ResourceIdJsonCommand(
                  'produto update',
                  'Atualiza parcialmente um produto',
                  $produtosClient->updateProduto(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'ID do produto',
                  'Payload JSON do produto',
                ),
                new ResourceIdCommand(
                  'produto delete',
                  'Exclui um produto',
                  $produtosClient->deleteProduto(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'ID do produto',
                ),
                new ResourceListCommand(
                  'produto categorias',
                  'Lista categorias de produtos',
                  $produtosClient->listCategoriasProduto(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca'],
                ),
                new ResourceListCommand(
                  'produto cest',
                  'Lista códigos CEST',
                  $produtosClient->listCest(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca', 'codigo' => 'codigo'],
                ),
                new ResourceListCommand(
                  'produto ncm',
                  'Lista códigos NCM',
                  $produtosClient->listNcm(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca', 'codigo' => 'codigo'],
                ),
                new ResourceListCommand(
                  'produto unidades-medida',
                  'Lista unidades de medida',
                  $produtosClient->listUnidadesMedida(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca', 'codigo' => 'codigo'],
                ),
                new ResourceListCommand(
                  'produto ecommerce-categorias',
                  'Lista categorias de ecommerce',
                  $produtosClient->listCategoriasEcommerce(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca'],
                ),
                new ResourceListCommand(
                  'produto ecommerce-marcas',
                  'Lista marcas de ecommerce',
                  $produtosClient->listMarcasEcommerce(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  ['busca' => 'busca'],
                ),
                new ResourceListCommand(
                  'servico list',
                  'Lista serviços por filtros',
                  $servicosClient->listServicos(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  $paginationValidator,
                  $serviceFilters,
                ),
                new ResourceJsonCommand(
                  'servico create',
                  'Cria um serviço',
                  $servicosClient->createServico(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'Payload JSON do serviço',
                ),
                new ResourceIdCommand(
                  'servico get',
                  'Busca um serviço por ID',
                  $servicosClient->getServico(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'ID do serviço',
                ),
                new ResourceIdJsonCommand(
                  'servico update',
                  'Atualiza parcialmente um serviço',
                  $servicosClient->updateServico(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'ID do serviço',
                  'Payload JSON do serviço',
                ),
                new ResourceJsonCommand(
                  'servico delete',
                  'Exclui serviços em lote',
                  $servicosClient->deleteServicos(...),
                  $errorEnvelope,
                  $jsonRenderer,
                  'Payload JSON com os IDs dos serviços',
                ),
              ]
            );
        } catch (\Throwable $e) {
            // Typically CA_CLIENT_ID / CA_CLIENT_SECRET missing, but anything
            // thrown here leaves the API commands unregistered. Remember why so
            // run() can explain it instead of silently offering an empty CLI.
            $this->bootstrapError = $e;
        }
    }


    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void {
        // Commands handle their own error output via ErrorEnvelope.
        // Suppress default Symfony rendering to keep stderr clean.
    }


    public function run(?InputInterface $input=NULL, ?OutputInterface $output=NULL): int {
        if ($input === NULL) {
            $rawArgv = $_SERVER['argv'] ?? [];
            $argv    = array_values(array_filter(is_array($rawArgv) ? $rawArgv : [], 'is_string'));
            $input   = $this->buildInput($argv);
        }

        // Structured logging is opt-in and goes to a JSONL file, never to stderr,
        // which stays reserved for the error envelope.
        if ($input->hasParameterOption(['--verbose', '-v', '-vv', '-vvv', '--debug'], TRUE)) {
            $this->logger?->enable();
        }

        if ($this->bootstrapError !== NULL && !$this->isAlwaysAvailableCommand($input)) {
            // client_error: the operator has to fix configuration; retrying as-is
            // can never succeed.
            (new ErrorEnvelope())->renderToStderr(
              new CliException(
                ErrorKind::ClientError,
                FALSE,
                'Falha ao inicializar o CLI: ' . $this->bootstrapError->getMessage(),
                NULL,
                NULL,
                Uuid::uuid4()->toString(),
                $this->bootstrapError,
              )
            );

            return 1;
        }

        try {
            return parent::run($input, $output);
        } catch (\Throwable) {
            return 1;
        }
    }


    /**
     * Discovery and help must keep working even when bootstrap failed — that is
     * how the operator finds out what to configure.
     */
    private function isAlwaysAvailableCommand(InputInterface $input): bool {
        $name = $input->getFirstArgument();

        return $name === NULL
            || in_array($name, ['list', 'help', 'completion'], TRUE)
            || $input->hasParameterOption(['--help', '-h', '--version', '-V'], TRUE);
    }


    protected function getDefaultInputDefinition(): InputDefinition {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(
          new InputOption(
            'debug',
            NULL,
            InputOption::VALUE_NONE,
            'Grava log estruturado em ~/.cache/conta-azul-cli/log.jsonl',
          )
        );

        return $definition;
    }


    /** @param list<string> $argv */
    private function buildInput(array $argv): ArgvInput {
        // Merge two-word command names (e.g. "auth login") that arrive as separate
        // argv tokens into a single token so Symfony's parser doesn't treat the
        // second word as a stray positional argument.
        if (
            isset($argv[1], $argv[2])
            && !str_starts_with($argv[1], '-')
            && !str_starts_with($argv[2], '-')
        ) {
            $compound = $argv[1] . ' ' . $argv[2];
            if ($this->has($compound)) {
                $argv = [$argv[0], $compound, ...array_slice($argv, 3)];
            }
        }

        return new ArgvInput($argv);
    }


}
