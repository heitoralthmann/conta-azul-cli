<?php

declare(strict_types=1);

namespace ContaAzulCli\Api;

final class FinanceiroClient extends BaseClient
{
    // -------------------------------------------------------------------------
    // Lançamentos
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listLancamentos(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/lancamentos', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    /** @return array<mixed> */
    public function getLancamento(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/lancamentos/{$id}");
    }

    // -------------------------------------------------------------------------
    // Contas a Receber
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listContasAReceber(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/contas-a-receber', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    /** @return array<mixed> */
    public function getContaAReceber(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas-a-receber/{$id}");
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createContaAReceber(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/contas-a-receber', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
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

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listContasAPagar(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/contas-a-pagar', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    /** @return array<mixed> */
    public function getContaAPagar(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas-a-pagar/{$id}");
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createContaAPagar(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/contas-a-pagar', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
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

    /** @return array<mixed> */
    public function getParcela(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/parcelas/{$id}");
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function baixarParcela(string $id, array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', "/v1/financeiro/parcelas/{$id}/baixar", ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    // -------------------------------------------------------------------------
    // Cobranças
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function listCobrancas(int $pagina = 1, int $tamanhoPagina = 50, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/cobrancas', [
            'query' => array_merge(['pagina' => $pagina, 'tamanho_pagina' => $tamanhoPagina], $filters),
        ]);
    }

    /** @return array<mixed> */
    public function getCobranca(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/cobrancas/{$id}");
    }

    // -------------------------------------------------------------------------
    // Contas Financeiras
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function listContasFinanceiras(): array
    {
        return $this->request('GET', '/v1/financeiro/contas');
    }

    /** @return array<mixed> */
    public function getSaldoContaFinanceira(string $id): array
    {
        return $this->request('GET', "/v1/financeiro/contas/{$id}/saldo");
    }

    // -------------------------------------------------------------------------
    // Categorias
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function listCategorias(): array
    {
        return $this->request('GET', '/v1/financeiro/categorias');
    }

    // -------------------------------------------------------------------------
    // Centros de Custo
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function listCentrosDeCusto(): array
    {
        return $this->request('GET', '/v1/financeiro/centros-de-custo');
    }

    // -------------------------------------------------------------------------
    // Transferências
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    public function createTransferencia(array $payload, int $pollTimeout = 60, bool $noWait = false): array
    {
        $response = $this->request('POST', '/v1/financeiro/transferencias', ['json' => $payload]);

        return $this->handleAsyncResponse($response, $pollTimeout, $noWait);
    }

    // -------------------------------------------------------------------------
    // Eventos Financeiros / Alterações
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array<mixed>
     */
    public function getAlteracoes(string $desde, array $filters = []): array
    {
        return $this->request('GET', '/v1/financeiro/eventos-financeiros/alteracoes', [
            'query' => array_merge(['desde' => $desde], $filters),
        ]);
    }

    // -------------------------------------------------------------------------
    // Protocolo
    // -------------------------------------------------------------------------

    /** @return array<mixed> */
    public function getProtocolo(string $id): array
    {
        return $this->request('GET', "/v1/protocolo/{$id}");
    }
}
