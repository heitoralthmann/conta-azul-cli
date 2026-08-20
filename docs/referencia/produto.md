<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Produtos

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

> **`GET /v1/produtos` ignora em silêncio todo parâmetro que não reconhece.**
> Um filtro com o nome errado não vira 400: vira `200` com o catálogo
> inteiro, como se tudo casasse. Por isso os filtros abaixo são só os que
> foram confirmados contra a produção — `--ids` e `--categoria-id`
> existiram até 2026-08-19 e foram removidos por não filtrarem nada.

## `produto list` ✅ { #produto-list }

`GET /v1/produtos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| `--busca` | não | — | Busca textual por nome |
| `--codigo` | não | — | Filtra pelo SKU; vai para a API como `sku` |
| `--status` | não | — | `ATIVO` ou `INATIVO` |

Retorna `{totalItems, items[]}` — camelCase, como `pessoa list`. Cada item
traz `id`, `id_legado`, `nome`, `codigo`, `tipo`, `status`, `saldo`,
`valor_venda`, `custo_medio`, `estoque_minimo`/`maximo`,
`integracao_ecommerce_ativada`, `ean` e `produtos_variacao[]`.

> O SKU aparece como `codigo` em `produto list` e como `codigo_sku` em
> `produto get` — três nomes para o mesmo dado, contando o `sku` da query.

## `produto create` ✅ { #produto-create }

`POST /v1/produtos`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON do produto |

**Só `nome` é obrigatório** — `{"nome":"…"}` basta. A API preenche o resto:
gera um `codigo_sku` derivado do nome, atribui a categoria `Outras`,
`status: ATIVO`, `formato: SIMPLES` e `versao: 0`. Retorna o produto
completo, já com `id` e `id_legado`.

<a id="produto-delete"></a>
## `produto get`, `produto delete` ✅ { #produto-get }

- `produto get` — `GET /v1/produtos/{id}`
- `produto delete` — `DELETE /v1/produtos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID do produto |

`produto get` devolve o cadastro completo, com os blocos aninhados
`categoria`, `estoque`, `fiscal` (`ncm`, `cest`, `unidade_medida`),
`ecommerce`, `variacao[]` e `detalhe_kit[]`.

`produto delete` responde **204 No Content** (renderizado como `[]`) e a
exclusão é permanente: `produto get` passa a devolver 404.

## `produto update` ✅ { #produto-update }

`PATCH /v1/produtos/{id}` — atualiza apenas os campos enviados.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID do produto |
| `--json` | **sim** | Objeto JSON da atualização |

Responde **204 No Content** (renderizado como `[]`) — confirme o resultado
com `produto get`. Cada atualização bem-sucedida **incrementa `versao`**,
mas, diferente de `baixa update`, a API não exige que você envie a versão
atual: não há controle de concorrência otimista aqui.

<a id="produto-cest"></a>
<a id="produto-ncm"></a>
<a id="produto-unidades-medida"></a>
<a id="produto-ecommerce-marcas"></a>
## Catálogos de produtos ✅ { #produto-categorias }

- `produto categorias` — `GET /v1/produtos/categorias`
- `produto cest` — `GET /v1/produtos/cest`
- `produto ncm` — `GET /v1/produtos/ncm`
- `produto unidades-medida` — `GET /v1/produtos/unidades-medida`
- `produto ecommerce-marcas` — `GET /v1/produtos/ecommerce-marcas`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| `--busca` | não | — | Filtro busca |
| `--codigo` | não | — | Filtro codigo |

Os comandos `produto categorias`, `produto cest`, `produto ncm`,
`produto unidades-medida` e `produto ecommerce-marcas` consultam,
respectivamente, `GET /v1/produtos/categorias`, `/cest`, `/ncm`,
`/unidades-medida` e `/ecommerce-marcas`.

Todos aceitam `--pagina`, `--tamanho-pagina` e `--busca`; CEST, NCM e
unidades de medida também aceitam `--codigo`.

Diferente de `produto list`, os catálogos respondem `{total_items, items[]}`
— **snake_case**. As duas listagens do mesmo recurso não usam a mesma
convenção de nome.

| Comando | Formato de item |
|---|---|
| `produto categorias` | `{id, uuid, descricao}` |
| `produto cest` | `{id, codigo, descricao}` |
| `produto ncm` | `{id, codigo, descricao}` |
| `produto unidades-medida` | `{id, descricao, abreviacao, em_uso}` |
| `produto ecommerce-marcas` | `{}` — a conta de teste não tem marcas cadastradas |

## `produto ecommerce-categorias` ⚠️ { #produto-ecommerce-categorias }

`GET /v1/produtos/ecommerce-categorias`

!!! warning "Não verificado"

    Escrito a partir da documentação e **nunca exercitado** contra a
    API. Path, nomes de filtro, campos do payload, tipo do id e formato
    da resposta são todos não confiáveis. Veja
    [Notas para quem for estender](../guia/estendendo.md).

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| `--busca` | não | — | Filtro busca |

Aceita `--pagina`, `--tamanho-pagina` e `--busca`.

> **Único comando do grupo que não foi possível verificar.** Em 2026-08-19
> ele respondeu `400` com
> `{"error":"Os filtros informados para busca de categoria de e-commerce são inválidos"}`
> em **todas** as tentativas — inclusive sem nenhum parâmetro, o que
> descarta a hipótese de filtro malformado. O path está certo: caminhos
> vizinhos inventados (`/ecommerce-categoria`, `/categorias-ecommerce`)
> caem na rota `/v1/produtos/{id}` e falham reclamando de uuid, enquanto
> este cai num handler de e-commerce de verdade. O irmão
> `produto ecommerce-marcas` responde `200` com lista vazia na mesma conta.
> A hipótese que sobra é uma pré-condição de conta (integração de
> e-commerce não configurada) que a API reporta como erro de filtro.
