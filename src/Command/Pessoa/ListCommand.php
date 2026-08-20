<?php

declare(strict_types=1);

namespace ContaAzulCli\Command\Pessoa;

use ContaAzulCli\Api\PaginationValidator;
use ContaAzulCli\Api\PessoasClient;
use ContaAzulCli\Command\Support\CommandExecutor;
use ContaAzulCli\Command\Support\PaginationOptions;
use ContaAzulCli\Output\ErrorEnvelope;
use ContaAzulCli\Output\ResponseRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

/** Lists people with pagination and API filter options. */
#[AsCommand(name: 'pessoa list', description: 'Lista pessoas por filtros')]
final class ListCommand extends Command
{
  private readonly CommandExecutor $commandExecutor;

  /** Creates the command and its API/output collaborators. */
  public function __construct(
      private readonly PessoasClient $client,
      private readonly ErrorEnvelope $errorEnvelope,
      private readonly ResponseRenderer $responseRenderer,
      private readonly PaginationValidator $paginationValidator,
      CommandExecutor|null $commandExecutor = null,
  ) {
    $this->commandExecutor = $commandExecutor ?? new CommandExecutor($errorEnvelope);

    parent::__construct();
  }

  /** Collects filters, lists people, and renders output or an error. */
  public function __invoke(
      #[Option(description: 'Campo de ordenação')]
      string|null $tipoOrdenacao = null,
      #[Option(description: 'Direção da ordenação')]
      string|null $ordemOrdenacao = null,
      #[Option(description: 'Busca por nome ou documento')]
      string|null $busca = null,
      #[Option(description: 'IDs das pessoas')]
      string|null $ids = null,
      #[Option(description: 'Documentos das pessoas')]
      string|null $documentos = null,
      #[Option(description: 'Países das pessoas')]
      string|null $paises = null,
      #[Option(description: 'Cidades das pessoas')]
      string|null $cidades = null,
      #[Option(description: 'UFs das pessoas')]
      string|null $ufs = null,
      #[Option(name: 'codigos-pessoa', description: 'Códigos das pessoas')]
      string|null $codigosPessoa = null,
      #[Option(description: 'Emails das pessoas')]
      string|null $emails = null,
      #[Option(name: 'tipos-pessoa', description: 'Tipos de pessoa')]
      string|null $tiposPessoa = null,
      #[Option(description: 'Nomes das pessoas')]
      string|null $nomes = null,
      #[Option(description: 'Telefones das pessoas')]
      string|null $telefones = null,
      #[Option(name: 'data-criacao-inicio', description: 'Data inicial de criação')]
      string|null $dataCriacaoInicio = null,
      #[Option(name: 'data-criacao-fim', description: 'Data final de criação')]
      string|null $dataCriacaoFim = null,
      #[Option(name: 'data-alteracao-de', description: 'Data inicial de alteração')]
      string|null $dataAlteracaoDe = null,
      #[Option(name: 'data-alteracao-ate', description: 'Data final de alteração')]
      string|null $dataAlteracaoAte = null,
      #[Option(name: 'tipo-perfil', description: 'Perfil da pessoa')]
      string|null $tipoPerfil = null,
      #[Option(name: 'com-endereco', description: 'Retorna apenas pessoas com endereço')]
      bool $comEndereco = false,
      #[Option(description: 'Número da página')]
      int $pagina = 1,
      #[Option(name: 'tamanho-pagina', description: 'Itens por página')]
      int $tamanhoPagina = 50,
  ): int {
    return $this->commandExecutor->execute(
        function () use (
            $tipoOrdenacao,
            $ordemOrdenacao,
            $busca,
            $ids,
            $documentos,
            $paises,
            $cidades,
            $ufs,
            $codigosPessoa,
            $emails,
            $tiposPessoa,
            $nomes,
            $telefones,
            $dataCriacaoInicio,
            $dataCriacaoFim,
            $dataAlteracaoDe,
            $dataAlteracaoAte,
            $tipoPerfil,
            $comEndereco,
            $pagina,
            $tamanhoPagina,
        ): void {
          $pagination = PaginationOptions::fromValues($pagina, $tamanhoPagina, $this->paginationValidator);

          $optionMap = [
            'tipo_ordenacao'      => $tipoOrdenacao,
            'ordem_ordenacao'     => $ordemOrdenacao,
            'busca'               => $busca,
            'ids'                 => $ids,
            'documentos'          => $documentos,
            'paises'              => $paises,
            'cidades'             => $cidades,
            'ufs'                 => $ufs,
            'codigos_pessoa'      => $codigosPessoa,
            'emails'              => $emails,
            'tipos_pessoa'        => $tiposPessoa,
            'nomes'               => $nomes,
            'telefones'           => $telefones,
            'data_criacao_inicio' => $dataCriacaoInicio,
            'data_criacao_fim'    => $dataCriacaoFim,
            'data_alteracao_de'   => $dataAlteracaoDe,
            'data_alteracao_ate'  => $dataAlteracaoAte,
            'tipo_perfil'         => $tipoPerfil,
          ];
          $filters   = [];
          foreach ($optionMap as $queryName => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filters[$queryName] = $value;
          }

          if ($comEndereco) {
              $filters['com_endereco'] = true;
          }

          $this->responseRenderer->render(
              $this->client->listPessoas($pagination->page(), $pagination->pageSize(), $filters),
          );
        },
    );
  }
}
