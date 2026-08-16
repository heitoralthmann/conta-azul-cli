<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Error\CliException;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pessoa list', description: 'Lista pessoas por filtros')]
final class ListCommand extends Command
{
    private readonly CommandExecutor $commandExecutor;


    public function __construct(
        private readonly PessoasClient $client,
        private readonly ErrorEnvelope $errorEnvelope,
        private readonly JsonRenderer $jsonRenderer,
        private readonly PaginationValidator $paginationValidator,
        ?CommandExecutor $commandExecutor=NULL,
    ) {
        $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);
        parent::__construct();
    }


    protected function configure(): void {
        $this
            ->addOption('tipo-ordenacao', NULL, InputOption::VALUE_REQUIRED, 'Campo de ordenação')
            ->addOption('ordem-ordenacao', NULL, InputOption::VALUE_REQUIRED, 'Direção da ordenação')
            ->addOption('busca', NULL, InputOption::VALUE_REQUIRED, 'Busca por nome ou documento')
            ->addOption('ids', NULL, InputOption::VALUE_REQUIRED, 'IDs das pessoas')
            ->addOption('documentos', NULL, InputOption::VALUE_REQUIRED, 'Documentos das pessoas')
            ->addOption('paises', NULL, InputOption::VALUE_REQUIRED, 'Países das pessoas')
            ->addOption('cidades', NULL, InputOption::VALUE_REQUIRED, 'Cidades das pessoas')
            ->addOption('ufs', NULL, InputOption::VALUE_REQUIRED, 'UFs das pessoas')
            ->addOption('codigos-pessoa', NULL, InputOption::VALUE_REQUIRED, 'Códigos das pessoas')
            ->addOption('emails', NULL, InputOption::VALUE_REQUIRED, 'Emails das pessoas')
            ->addOption('tipos-pessoa', NULL, InputOption::VALUE_REQUIRED, 'Tipos de pessoa')
            ->addOption('nomes', NULL, InputOption::VALUE_REQUIRED, 'Nomes das pessoas')
            ->addOption('telefones', NULL, InputOption::VALUE_REQUIRED, 'Telefones das pessoas')
            ->addOption('data-criacao-inicio', NULL, InputOption::VALUE_REQUIRED, 'Data inicial de criação')
            ->addOption('data-criacao-fim', NULL, InputOption::VALUE_REQUIRED, 'Data final de criação')
            ->addOption('data-alteracao-de', NULL, InputOption::VALUE_REQUIRED, 'Data inicial de alteração')
            ->addOption('data-alteracao-ate', NULL, InputOption::VALUE_REQUIRED, 'Data final de alteração')
            ->addOption('tipo-perfil', NULL, InputOption::VALUE_REQUIRED, 'Perfil da pessoa')
            ->addOption('com-endereco', NULL, InputOption::VALUE_NONE, 'Retorna apenas pessoas com endereço');
        PaginationOptions::configure($this);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->commandExecutor->execute(
          function () use ($input): void {
            $pagination = PaginationOptions::fromInput($input, $this->paginationValidator);

            $optionMap = [
                'tipo-ordenacao'    => 'tipo_ordenacao',
                'ordem-ordenacao'   => 'ordem_ordenacao',
                'busca'             => 'busca',
                'ids'               => 'ids',
                'documentos'        => 'documentos',
                'paises'            => 'paises',
                'cidades'           => 'cidades',
                'ufs'               => 'ufs',
                'codigos-pessoa'    => 'codigos_pessoa',
                'emails'            => 'emails',
                'tipos-pessoa'      => 'tipos_pessoa',
                'nomes'             => 'nomes',
                'telefones'         => 'telefones',
                'data-criacao-inicio' => 'data_criacao_inicio',
                'data-criacao-fim'    => 'data_criacao_fim',
                'data-alteracao-de'   => 'data_alteracao_de',
                'data-alteracao-ate'  => 'data_alteracao_ate',
                'tipo-perfil'       => 'tipo_perfil',
            ];
            $filters = [];
            foreach ($optionMap as $option => $queryName) {
                $value = $input->getOption($option);
                if (is_string($value) && $value !== '') {
                    $filters[$queryName] = $value;
                }
            }
            if ((bool) $input->getOption('com-endereco')) {
                $filters['com_endereco'] = TRUE;
            }

            $this->jsonRenderer->render($this->client->listPessoas($pagination->page(), $pagination->pageSize(), $filters));
          }
        );
    }


}
