<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Contas a pagar

## `conta-a-pagar list` ✅ { #conta-a-pagar-list }

`GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-vencimento-de` | não¹ | 1º dia do mês corrente | Vencimento inicial (`YYYY-MM-DD`) |
| `--data-vencimento-ate` | não¹ | último dia do mês corrente | Vencimento final (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

> O `id` de cada item é o da **parcela**, mesma regra de `conta-a-receber list`.

Formato de resposta igual ao de contas a receber, trocando `cliente` por fornecedor.

## `conta-a-pagar create` ✅ { #conta-a-pagar-create }

`POST /v1/financeiro/eventos-financeiros/contas-a-pagar` — **escrita assíncrona**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--json` | **sim** | — | Payload JSON, repassado à API **verbatim** |
| `--poll-timeout` | não | `60` | Segundos aguardando a confirmação assíncrona |
| `--no-wait` | não | — | Retorna o protocolo na hora, sem aguardar |

Mesmo payload de `conta-a-receber create` (inclusive `detalhe_valor` e o
`condicao_pagamento.parcelas`), trocando a categoria do rateio por uma de
`tipo` `DESPESA`. Vale a mesma advertência: **a API não publica `DELETE` de
evento financeiro**, então conta a pagar criada por engano só sai pela
interface web.
