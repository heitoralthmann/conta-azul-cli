<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Orçamentos

O payload de criação segue o schema da API e é enviado sem transformação. Use `--json` com um objeto JSON.

**Verificado contra produção em 2026-08-19** — os quatro comandos, com três
orçamentos de teste criados e excluídos. Os nove filtros de `orcamento list`
passaram na receita completa (baseline, `zzz_bogus=abc`, valor
discriminante). Como em `venda`, os problemas estavam fora da listagem — e
aqui há dois graves o suficiente para virem antes das tabelas:

> **1. `total_itens` não conta tudo o que `itens` devolve.** A listagem sem
> filtro responde `total_itens: 157` e entrega **158** orçamentos distintos.
> A diferença é a situação `ORCAMENTO_RECUSADO`: filtrando
> `situacoes=ORCAMENTO` os dois números batem (160/160), e filtrando
> `situacoes=ORCAMENTO_RECUSADO` a resposta traz um item com
> `total_itens: 0`. Quem paginar por `total_itens` **perde registros** —
> conte `itens`.
>
> **2. `observacoes` e `observacoes_pagamento` trocam de lugar entre
> escrita e leitura.** O que você manda no `POST` como `observacoes` volta
> no `GET` como `observacoes_pagamento`, e vice-versa. Confirmado com um
> orçamento criado com os dois campos preenchidos com textos distintos, mais
> `descricao` e `previsao_entrega` como controle — esses dois voltam no
> lugar certo. O CLI **não corrige** a troca: `--json` é repassado sem
> transformação, e compensar aqui quebraria no dia em que a API consertar.

## `orcamento list` ✅ { #orcamento-list }

`GET /v1/orcamentos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página (aceita até `1000`) |
| `--campo-ordenado-ascendente` | não | — | Filtro campo_ordenado_ascendente |
| `--campo-ordenado-descendente` | não | — | Filtro campo_ordenado_descendente |
| `--data-alteracao-ate` | não | — | Filtro data_alteracao_ate |
| `--data-alteracao-de` | não | — | Filtro data_alteracao_de |
| `--data-criacao-ate` | não | — | Filtro data_criacao_ate |
| `--data-criacao-de` | não | — | Filtro data_criacao_de |
| `--data-fim` | não | — | Filtro data_fim |
| `--data-inicio` | não | — | Filtro data_inicio |
| `--termo-busca` | não | — | Filtro termo_busca |

Retorna `{itens[], total_itens}` — com a ressalva sobre `total_itens` acima.

**Os pares de data são tudo-ou-nada.** Omitir os dois lados é válido (o
intervalo é opcional), mas mandar **um só** devolve `400`:

| Par | Formato | Erro se vier só um lado |
|---|---|---|
| `--data-inicio` / `--data-fim` | `YYYY-MM-DD` | "É necessário informar ambos os parâmetros de data" |
| `--data-criacao-de` / `--data-criacao-ate` | `YYYY-MM-DD` | "…ambos os parâmetros de data de criação" |
| `--data-alteracao-de` / `--data-alteracao-ate` | **`YYYY-MM-DDTHH:MM:SS`** | "…ambos os parâmetros de data de alteração" |

O par de alteração é o único que **exige data-time ISO 8601**: com
`2024-11-18` a API responde `400` pedindo o formato
`2025-10-20T07:59:59`. Os outros dois pares recusam justamente esse formato
estendido — não são intercambiáveis.

`--termo-busca` casa nome do cliente e número do orçamento.
`--campo-ordenado-*` não muda a contagem, mas um valor fora de
`[CLIENTE, DATA, NUMERO]` devolve `400` — foi assim que se provou que o
parâmetro é lido, e não descartado.

**Filtros por array não expostos.** A API também aceita `ids_vendedores`,
`ids_clientes`, `ids_natureza_operacao`, `ids_categorias`, `ids_produtos`,
`situacoes`, `origens`, `numeros`, `ids_legado_donos`, `ids_legado_clientes`
e `ids_legado_produtos`. Eles são **reais** — `situacoes`, `numeros` e
`ids_clientes` foram exercitados e filtraram corretamente — e aceitam tanto
um valor único quanto vários repetidos (`numeros=1&numeros=2`) ou separados
por vírgula (`numeros=1,2`); a forma `numeros[]` devolve `400`. Ou seja: um
valor escalar já funciona, então a razão antiga para não expô-los ("o
comando genérico só suporta filtros escalares") não se sustenta. Continuam
fora da CLI por decisão de escopo, não por impedimento técnico.
O enum de `situacoes` é `ORCAMENTO`, `ORCAMENTO_ACEITO` ou
`ORCAMENTO_RECUSADO`.

## `orcamento create` ✅ { #orcamento-create }

`POST /v1/orcamentos` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do orçamento (ver campos obrigatórios abaixo) |

Quatro campos obrigatórios, e a documentação acertou desta vez:

| Campo | Formato |
|---|---|
| `id_cliente` | uuid da pessoa |
| `data_orcamento` | `YYYY-MM-DD` |
| `data_validade` | `YYYY-MM-DD` |
| `itens` | array de `{id, quantidade, valor}` — `id` é o uuid do produto ou serviço |

`quantidade` e `valor` precisam ser **maiores que zero**; a API recusa `0`
com mensagem própria para cada um.

Ao contrário de `venda create`, **`numero` não é aceito nem exigido** — a API
atribui sozinha, e do **mesmo contador das vendas**: com `venda
proximo-numero` em 7297, o orçamento criado em seguida saiu como 7297.
`situacao` também não entra no payload; todo orçamento nasce `ORCAMENTO`.

Retorna apenas `{id}`.

Payload mínimo que passou:

```json
{
  "id_cliente": "11111111-1111-4111-8111-111111111111",
  "data_orcamento": "2026-08-19",
  "data_validade": "2026-09-19",
  "descricao": "TESTE CLI CONTA AZUL",
  "itens": [{ "id": "22222222-2222-4222-8222-222222222222", "quantidade": 1, "valor": 10 }]
}
```

## `orcamento get` ✅ { #orcamento-get }

`GET /v1/orcamentos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do orçamento |

Devolve o orçamento **solto**, sem envelope — ao contrário de `venda get` — e
com os `itens` inclusos, também ao contrário de `venda get`. Id inexistente
devolve `404` com `"Orçamento não encontrado com o ID informado"`.

Lembre da troca de `observacoes` ↔ `observacoes_pagamento` ao ler o que você
mesmo escreveu.

## `orcamento excluir-lote` ✅ { #orcamento-excluir-lote }

`DELETE /v1/orcamentos` — exclui orçamentos em lote.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON com `{"ids": [...]}` — de 1 a 10 uuids por chamada |

Resposta `204 No Content` — sem corpo, que o CLI imprime como `[]`. Os dois
limites são cobrados: `[]` e 11 ids devolvem `400`.

**A exclusão é física**, ao contrário de `venda excluir-lote` e `servico
delete`: depois da chamada, `orcamento get` responde `404`. É a mesma
semântica de `produto delete`.

**O `204` não diz o que foi excluído.** Um uuid inexistente responde `204`
igual, sem os contadores `{atualizados, ignorados}` que `venda excluir-lote`
devolve. A única confirmação é reler a listagem.

Não existe exclusão individual: `DELETE /v1/orcamentos/{id}` responde `405`.
E o grupo **não tem update** — `PUT /v1/orcamentos/{id}` também responde
`405`, então os quatro comandos são a superfície completa do recurso.
