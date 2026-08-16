<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/** Cliente dos endpoints de serviços da API Conta Azul. */
final class ServicosClient extends BaseClient
{
    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listServicos(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/servicos', [
            'query' => array_merge([
                'pagina' => $pagina,
                'tamanho_pagina' => $tamanhoPagina,
            ], $filters),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createServico(array $payload): array
    {
        return $this->request('POST', '/v1/servicos', ['json' => $payload]);
    }

    /** @return array<mixed> */
    public function getServico(string $id): array
    {
        return $this->request('GET', '/v1/servicos/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function updateServico(string $id, array $payload): array
    {
        return $this->request('PATCH', '/v1/servicos/' . rawurlencode($id), ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function deleteServicos(array $payload): array
    {
        return $this->request('DELETE', '/v1/servicos', ['json' => $payload]);
    }
}
