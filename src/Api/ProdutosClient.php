<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Cliente dos endpoints de produtos da API Conta Azul. */
final class ProdutosClient extends BaseClient
{


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listProdutos(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createProduto(array $payload): array {
        return $this->request('POST', '/v1/produtos', ['json' => $payload]);
    }


    /** @return array<mixed> */
    public function getProduto(string $id): array {
        return $this->request('GET', '/v1/produtos/'.rawurlencode($id));
    }


    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function updateProduto(string $id, array $payload): array {
        return $this->request('PATCH', '/v1/produtos/'.rawurlencode($id), ['json' => $payload]);
    }


    /** @return array<mixed> */
    public function deleteProduto(string $id): array {
        return $this->request('DELETE', '/v1/produtos/'.rawurlencode($id));
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listCategoriasProduto(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/categorias', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listCest(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/cest', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listNcm(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/ncm', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listUnidadesMedida(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/unidades-medida', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listCategoriasEcommerce(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/ecommerce-categorias', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listMarcasEcommerce(int $pagina=1, int $tamanhoPagina=50, array $filters=[]): array {
        return $this->listResource('/v1/produtos/ecommerce-marcas', $pagina, $tamanhoPagina, $filters);
    }


    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    private function listResource(string $path, int $pagina, int $tamanhoPagina, array $filters): array {
        return $this->request(
          'GET', $path, [
            'query' => array_merge(
              [
                'pagina' => $pagina,
                'tamanho_pagina' => $tamanhoPagina,
              ], $filters
            ),
          ]
        );
    }


}
