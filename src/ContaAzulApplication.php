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
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Error\ErrorKind;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use ContaAzulCli\Output\Logger;
use ContaAzulCli\Output\Redactor;
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
    private ?Logger $logger = null;
    private ?\Throwable $bootstrapError = null;

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

        try {
            $config = new Configuration();
            $redactor = new Redactor();
            $logger = new Logger($redactor);
            $this->logger = $logger;
            $httpClient = HttpClient::create();
            $tokenStore = new TokenStore($config);
            $oauthClient = new OAuthClient($httpClient, $config);
            $authManager = new AuthManager($tokenStore, $oauthClient, $config);
            $callbackServer = new CallbackServer(certFile: $config->getCallbackCertFile(), keyFile: $config->getCallbackKeyFile());
            $client = new FinanceiroClient($config, $authManager, $logger, $redactor, $httpClient);

            $this->addCommands([
                new LoginCommand($authManager, $callbackServer, $errorEnvelope),
                new LogoutCommand($authManager),
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
        } catch (\Throwable $e) {
            // Typically CA_CLIENT_ID / CA_CLIENT_SECRET missing, but anything
            // thrown here leaves the API commands unregistered. Remember why so
            // run() can explain it instead of silently offering an empty CLI.
            $this->bootstrapError = $e;
        }
    }

    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        // Commands handle their own error output via ErrorEnvelope.
        // Suppress default Symfony rendering to keep stderr clean.
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if ($input === null) {
            $rawArgv = $_SERVER['argv'] ?? [];
            $argv    = array_values(array_filter(is_array($rawArgv) ? $rawArgv : [], 'is_string'));
            $input   = $this->buildInput($argv);
        }

        // Structured logging is opt-in and goes to a JSONL file, never to stderr,
        // which stays reserved for the error envelope.
        if ($input->hasParameterOption(['--verbose', '-v', '-vv', '-vvv', '--debug'], true)) {
            $this->logger?->enable();
        }

        if ($this->bootstrapError !== null && !$this->isAlwaysAvailableCommand($input)) {
            // client_error: the operator has to fix configuration; retrying as-is
            // can never succeed.
            (new ErrorEnvelope())->renderToStderr(new CliException(
                ErrorKind::ClientError,
                false,
                'Falha ao inicializar o CLI: ' . $this->bootstrapError->getMessage(),
                null,
                null,
                Uuid::uuid4()->toString(),
                $this->bootstrapError,
            ));

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
    private function isAlwaysAvailableCommand(InputInterface $input): bool
    {
        $name = $input->getFirstArgument();

        return $name === null
            || in_array($name, ['list', 'help', 'completion'], true)
            || $input->hasParameterOption(['--help', '-h', '--version', '-V'], true);
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            'debug',
            null,
            InputOption::VALUE_NONE,
            'Grava log estruturado em ~/.cache/conta-azul-cli/log.jsonl',
        ));

        return $definition;
    }

    /** @param list<string> $argv */
    private function buildInput(array $argv): ArgvInput
    {
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
