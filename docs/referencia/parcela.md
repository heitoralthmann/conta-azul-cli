<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Parcelas

## `parcela get` ✅ { #parcela-get }

`GET /v1/financeiro/eventos-financeiros/parcelas/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. ID da parcela — o `id` devolvido pelas buscas de contas |

Retorna a parcela com o evento financeiro aninhado em `evento`, incluindo `evento.id`, `condicao_pagamento` e `rateio[]`.

## `parcela update` ✅ { #parcela-update }

`PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` — **escrita síncrona** (`200`).

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |
| `--json` | **sim** | Payload JSON da parcela; `versao` (a versão atual) é obrigatório |

Atualiza `nota`, `descricao`, `vencimento`, `composicao_valor`,
`data_pagamento_esperado`, `metodo_pagamento`, `perda`, `nsu`,
`pagamento_agendado` e `id_conta_financeira`. Devolve a parcela atualizada,
já com a `versao` nova.

**Controle de concorrência otimista:** sem `versao` a resposta é **`409`**,
não `400` — mesma regra de `baixa update`.

> **Este endpoint não dá baixa.** Ele atualiza a parcela. Até 2026-08-19 o
> CLI o chamava como se quitasse (`parcela baixar`, mandando `{valor, data}`)
> e, exercitado, ele respondeu `200` sem registrar pagamento nenhum: nenhum
> dos dois campos existe no schema, e a API descarta campo desconhecido em
> silêncio também **no corpo da escrita**, não só na query. O `409` por falta
> de `versao` escondia isso — o comando nunca chegava a "funcionar" errado.

## `parcela baixar` ✅ { #parcela-baixar }

`POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` — **escrita síncrona** (`200`). Atalho para `baixa create`, montando o payload mínimo a partir de três opções.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |
| `--valor` | **sim** | Valor da baixa (ex: `100.50`) |
| `--data` | **sim** | Data da baixa (`YYYY-MM-DD`) |
| `--conta-financeira` | **sim** | Uuid da conta financeira que recebe a baixa |

síncrona** (`200`). Atalho para `baixa create`, montando o payload mínimo a
partir de três opções.


> O subrecurso `/baixa` **existe** — a nota anterior, de que a baixa seria um
> `PATCH` na parcela, estava errada. `--poll-timeout` e `--no-wait` saíram:
> a escrita é síncrona e nunca devolveu protocolo.

## `parcela list` ✅ { #parcela-list }

`GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id-evento>` | **sim** | Argumento posicional. ID do evento financeiro (`evento.id` aninhado na resposta de `parcela get`) |

Sem paginação: a API devolve **um array puro**, não um envelope — não há
`itens` nem contador. Cada item tem o mesmo formato de `parcela get`.

> O `id` do evento sai de `parcela get` → `evento.id`, ou do
> `evento_financeiro_id` que `protocolo get` devolve depois de uma criação.
