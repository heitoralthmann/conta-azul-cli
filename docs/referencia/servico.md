<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Serviços

Os payloads seguem o schema da API e são enviados sem transformação.

> **Serviços têm duas identidades, e os comandos não usam a mesma.**
> `id` é o uuid — é o que `servico get` e `servico update` consomem.
> `id_servico` é o inteiro legado — é o que **`servico delete` exige**.
> Trocar um pelo outro no delete devolve 400 reclamando de `int64`.

## `servico list` ✅ { #servico-list }

`GET /v1/servicos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página — aqui **só `10`, `20`, `50` ou `100`** |
| `--busca` | não | — | Busca textual pela descrição; vai para a API como `busca_textual` |

Retorna `{itens[], paginacao}` — **uma terceira convenção**, diferente do
`{totalItems, items[]}` de `pessoa`/`produto list` e do
`{total_items, items[]}` dos catálogos de produto. A contagem fica em
`paginacao.total_itens`, ao lado de `pagina_atual`, `total_paginas` e
`tamanho_pagina`.

Cada item traz `id`, `id_servico`, `codigo`, `descricao`, `preco`, `custo`,
`status`, `tipo_servico`, `codigo_cnae`, `lei_116`,
`codigo_municipio_servico`, `id_externo`, `lista_cenario_tributario[]` e
`natureza_operacional`.

> ⚠️ **`--tamanho-pagina` aceita menos valores aqui do que o validador do
> CLI.** A validação local libera `200`, `500` e `1000`, mas este endpoint
> os rejeita com 400 (`deve ser um dos seguintes valores: 10, 20, 50 ou
> 100`). Mesma restrição de `nota-fiscal list`.

> **Este endpoint honra um único filtro.** `GET /v1/servicos` responde 200 e
> descarta em silêncio qualquer parâmetro que não reconheça, então um nome
> errado devolve a lista inteira em vez de erro. `--codigo`, `--ids` e
> `--status` existiram até 2026-08-19 e foram removidos: nenhum nome
> testado (`codigo`, `codigos`, `codigo_servico`, `sku`, `ids`, `id`,
> `uuid`, `uuids`, `id_servico`, `status`, `situacao`, `ativo`, …) filtrava
> coisa alguma. Ordenação (`campo_ordenado_ascendente`/`descendente`) também
> não existe aqui, diferente de `contrato list`.

## `servico create` ✅ { #servico-create }

`POST /v1/servicos` — resposta **201 Created** com o serviço completo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON do serviço |

Três campos são obrigatórios, cobrados um erro por vez:

| Campo | Valores |
|---|---|
| `descricao` | texto livre |
| `status` | `ATIVO` ou `INATIVO` |
| `tipo_servico` | `PRESTADO`, `TOMADO` ou `AMBOS` |

```json
{"descricao":"…","status":"ATIVO","tipo_servico":"PRESTADO"}
```

> Diferente de `produto create`, que se vira só com `nome`, aqui não há
> default: sem `status` e `tipo_servico` a criação falha.

<a id="servico-update"></a>
## `servico get`, `servico update` ✅ { #servico-get }

- `servico get` — `GET /v1/servicos/{id}`
- `servico update` — `PATCH /v1/servicos/{id}`

Ambos pelo **uuid**.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID do serviço |
| `--json` | não | Payload JSON do serviço |

`servico get` recebe `<id>`. `servico update` recebe `<id>` e `--json` com
os campos a atualizar; responde **204 No Content** (renderizado como `[]`),
então confirme o resultado com `servico get`.

## `servico delete` ✅ { #servico-delete }

`DELETE /v1/servicos` — exclui serviços em lote. Resposta **204 No Content** (renderizada como `[]`).

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | `{"ids":[…]}` com os **`id_servico` inteiros**, não os uuids |

(renderizada como `[]`).


```json
{"ids":[495926356]}
```

> **É exclusão lógica, e ela não aparece em `servico get`.** Depois do
> delete o serviço some das listagens — `servico list` deixa de trazê-lo e a
> contagem cai —, mas `servico get` pelo uuid **continua respondendo 200**
> com o registro intacto e `status` ainda `ATIVO`. Não dá para descobrir por
> `servico get` que um serviço foi excluído; use `servico list`. É o oposto
> de `produto delete`, que passa a devolver 404.
