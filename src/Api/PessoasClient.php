<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

/**
 * Cliente dos endpoints de Pessoas da API Conta Azul.
 *
 * Os payloads de criação e atualização são repassados sem normalização: o
 * schema completo é mantido pela API e pode evoluir sem exigir mudanças no
 * CLI a cada campo novo.
 */
final class PessoasClient extends BaseClient
{
    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listPessoas(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/pessoas', [
            'query' => array_merge([
                'pagina'        => $pagina,
                'tamanho_pagina' => $tamanhoPagina,
            ], $filters),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createPessoa(array $payload): array
    {
        return $this->request('POST', '/v1/pessoas', ['json' => $payload]);
    }

    /** @return array<mixed> */
    public function getPessoa(string $id): array
    {
        return $this->request('GET', '/v1/pessoas/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function updatePessoa(string $id, array $payload): array
    {
        return $this->request('PUT', '/v1/pessoas/' . rawurlencode($id), ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function patchPessoa(string $id, array $payload): array
    {
        return $this->request('PATCH', '/v1/pessoas/' . rawurlencode($id), ['json' => $payload]);
    }

    /** @return array<mixed> */
    public function getPessoaLegado(string $id): array
    {
        return $this->request('GET', '/v1/pessoas/legado/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function activatePessoas(array $payload): array
    {
        return $this->request('POST', '/v1/pessoas/ativar', ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function deactivatePessoas(array $payload): array
    {
        return $this->request('POST', '/v1/pessoas/inativar', ['json' => $payload]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function deletePessoas(array $payload): array
    {
        return $this->request('POST', '/v1/pessoas/excluir', ['json' => $payload]);
    }

    /** @return array<mixed> */
    public function getContaConectada(): array
    {
        return $this->request('GET', '/v1/pessoas/conta-conectada');
    }
}
