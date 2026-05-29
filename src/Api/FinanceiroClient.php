<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

final class FinanceiroClient extends BaseClient
{
    // -------------------------------------------------------------------------
    // Lançamentos
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $filters */
    public function listLancamentos(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/lancamentos', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    public function getLancamento(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/lancamentos/{$id}");
    }

    // -------------------------------------------------------------------------
    // Contas a Receber
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $filters */
    public function listContasAReceber(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/contas-a-receber', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    public function getContaAReceber(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas-a-receber/{$id}");
    }

    /** @param array<string, mixed> $payload */
    public function createContaAReceber(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/contas-a-receber', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    /** @param array<string, mixed> $payload */
    public function updateContaAReceber(string $id, array $payload): array
    {
        return $this->request('PUT', "/v1/financeiro/contas-a-receber/{$id}", ['json' => $payload]);
    }

    public function deleteContaAReceber(string $id): void
    {
        $this->request('DELETE', "/v1/financeiro/contas-a-receber/{$id}");
    }

    // -------------------------------------------------------------------------
    // Contas a Pagar
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $filters */
    public function listContasAPagar(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/contas-a-pagar', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    public function getContaAPagar(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas-a-pagar/{$id}");
    }

    /** @param array<string, mixed> $payload */
    public function createContaAPagar(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/contas-a-pagar', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    /** @param array<string, mixed> $payload */
    public function updateContaAPagar(string $id, array $payload): array
    {
        return $this->request('PUT', "/v1/financeiro/contas-a-pagar/{$id}", ['json' => $payload]);
    }

    public function deleteContaAPagar(string $id): void
    {
        $this->request('DELETE', "/v1/financeiro/contas-a-pagar/{$id}");
    }

    // -------------------------------------------------------------------------
    // Parcelas
    // -------------------------------------------------------------------------

    public function getParcela(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/parcelas/{$id}");
    }

    /** @param array<string, mixed> $payload */
    public function baixarParcela(string $id, array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', "/v1/financeiro/parcelas/{$id}/baixar", ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    // -------------------------------------------------------------------------
    // Cobranças
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $filters */
    public function listCobrancas(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/cobrancas', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    public function getCobranca(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/cobrancas/{$id}");
    }

    // -------------------------------------------------------------------------
    // Contas Financeiras
    // -------------------------------------------------------------------------

    public function listContasFinanceiras(): array
    {
        return $this->request('GET', '/v1/financeiro/contas');
    }

    public function getSaldoContaFinanceira(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas/{$id}/saldo");
    }

    // -------------------------------------------------------------------------
    // Categorias
    // -------------------------------------------------------------------------

    public function listCategorias(): array
    {
        return $this->request('GET', '/v1/financeiro/categorias');
    }

    // -------------------------------------------------------------------------
    // Centros de Custo
    // -------------------------------------------------------------------------

    public function listCentrosDeCusto(): array
    {
        return $this->request('GET', '/v1/financeiro/centros-de-custo');
    }

    // -------------------------------------------------------------------------
    // Transferências
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    public function createTransferencia(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/transferencias', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    // -------------------------------------------------------------------------
    // Eventos Financeiros / Alterações
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $filters */
    public function getAlteracoes(string $desde, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/eventos-financeiros/alteracoes', [
            'query' => array_merge(['desde' => $desde], $filters),
        ]);
    }

    // -------------------------------------------------------------------------
    // Protocolo
    // -------------------------------------------------------------------------

    public function getProtocolo(string $id): array
    {
        return $this->request('GET', "/v1/protocolo/{$id}");
    }
}
