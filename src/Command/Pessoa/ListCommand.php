<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\JsonRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;

/** Lists people with pagination and API filter options. */
#[AsCommand(name: 'pessoa list', description: 'Lista pessoas por filtros')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly JsonRenderer $jsonRenderer,
      private readonly PaginationValidator $paginationValidator,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Declares person filters and shared pagination options. */
  protected function configure(): void
  {
    $this
          ->addOption('tipo-ordenacao', null, InputOption::VALUE_REQUIRED, 'Campo de ordenação')
          ->addOption('ordem-ordenacao', null, InputOption::VALUE_REQUIRED, 'Direção da ordenação')
          ->addOption('busca', null, InputOption::VALUE_REQUIRED, 'Busca por nome ou documento')
          ->addOption('ids', null, InputOption::VALUE_REQUIRED, 'IDs das pessoas')
          ->addOption('documentos', null, InputOption::VALUE_REQUIRED, 'Documentos das pessoas')
          ->addOption('paises', null, InputOption::VALUE_REQUIRED, 'Países das pessoas')
          ->addOption('cidades', null, InputOption::VALUE_REQUIRED, 'Cidades das pessoas')
          ->addOption('ufs', null, InputOption::VALUE_REQUIRED, 'UFs das pessoas')
          ->addOption('codigos-pessoa', null, InputOption::VALUE_REQUIRED, 'Códigos das pessoas')
          ->addOption('emails', null, InputOption::VALUE_REQUIRED, 'Emails das pessoas')
          ->addOption('tipos-pessoa', null, InputOption::VALUE_REQUIRED, 'Tipos de pessoa')
          ->addOption('nomes', null, InputOption::VALUE_REQUIRED, 'Nomes das pessoas')
          ->addOption('telefones', null, InputOption::VALUE_REQUIRED, 'Telefones das pessoas')
          ->addOption('data-criacao-inicio', null, InputOption::VALUE_REQUIRED, 'Data inicial de criação')
          ->addOption('data-criacao-fim', null, InputOption::VALUE_REQUIRED, 'Data final de criação')
          ->addOption('data-alteracao-de', null, InputOption::VALUE_REQUIRED, 'Data inicial de alteração')
          ->addOption('data-alteracao-ate', null, InputOption::VALUE_REQUIRED, 'Data final de alteração')
          ->addOption('tipo-perfil', null, InputOption::VALUE_REQUIRED, 'Perfil da pessoa')
          ->addOption('com-endereco', null, InputOption::VALUE_NONE, 'Retorna apenas pessoas com endereço');
    PaginationOptions::configure($this);
  }

  /** Collects filters, lists people, and renders output or an error. */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
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
          $filters   = [];
          foreach ($optionMap as $option => $queryName) {
              $value = $input->getOption($option);
            if (! is_string($value) || $value === '') {
                continue;
            }

            $filters[$queryName] = $value;
          }

          if ((bool) $input->getOption('com-endereco')) {
              $filters['com_endereco'] = true;
          }

          $this->jsonRenderer->render(
              $this->client->listPessoas($pagination->page(), $pagination->pageSize(), $filters),
          );
        },
    );
  }
}
