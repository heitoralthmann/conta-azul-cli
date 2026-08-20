<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Pessoas / Fornecedores

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

> **Os enums vão acentuados e capitalizados como na interface**, não em
> `SCREAMING_SNAKE_CASE`: `tipo_pessoa` aceita `Física`, `Jurídica` ou
> `Estrangeira`; `tipo_perfil` aceita `Cliente`, `Fornecedor` ou
> `Transportadora`. Enviar `FISICA` ou `CLIENTE` devolve **400**.

## `pessoa list` ✅ { #pessoa-list }

`GET /v1/pessoas`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--tipo-ordenacao` | não | — | Campo de ordenação |
| `--ordem-ordenacao` | não | — | Direção da ordenação |
| `--busca` | não | — | Busca por nome ou documento |
| `--ids` | não | — | IDs das pessoas |
| `--documentos` | não | — | Documentos das pessoas |
| `--paises` | não | — | Países das pessoas |
| `--cidades` | não | — | Cidades das pessoas |
| `--ufs` | não | — | UFs das pessoas |
| `--codigos-pessoa` | não | — | Códigos das pessoas |
| `--emails` | não | — | Emails das pessoas |
| `--tipos-pessoa` | não | — | Tipos de pessoa |
| `--nomes` | não | — | Nomes das pessoas |
| `--telefones` | não | — | Telefones das pessoas |
| `--data-criacao-inicio` | não | — | Data inicial de criação |
| `--data-criacao-fim` | não | — | Data final de criação |
| `--data-alteracao-de` | não | — | Data inicial de alteração |
| `--data-alteracao-ate` | não | — | Data final de alteração |
| `--tipo-perfil` | não | — | Perfil da pessoa |
| `--com-endereco` | não | — | Retorna apenas pessoas com endereço |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Retorna `{totalItems, items[]}` — **camelCase e em inglês**, diferente do
`{itens_totais, itens[]}` dos comandos financeiros. Quando nada casa,
`items` vem `null`, não `[]`.

> **Os dois pares de data não usam o mesmo formato**, e trocá-los devolve 400:
>
> | Filtro | Formato | Exemplo |
> |---|---|---|
> | `--data-criacao-inicio` / `--data-criacao-fim` | `YYYY-MM-DD` | `2026-08-19` |
> | `--data-alteracao-de` / `--data-alteracao-ate` | ISO 8601 **sem timezone** | `2026-08-19T00:00:00` |

## `pessoa create` ✅ { #pessoa-create }

`POST /v1/pessoas`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON da pessoa |

O mínimo aceito é `nome`, `tipo_pessoa` e `perfis`:

```json
{"nome":"…","tipo_pessoa":"Física","perfis":[{"tipo_perfil":"Cliente"}]}
```

Retorna `{id, tipo_pessoa, nome, ativo, origem, perfis, estrangeiro}`. A
criação é bem mais permissiva que `pessoa update` — nem documento, nem
endereço, nem contato são exigidos aqui.

<a id="pessoa-legado"></a>
## `pessoa get`, `pessoa legado` ✅ { #pessoa-get }

- `pessoa get` — `GET /v1/pessoas/{id}`
- `pessoa legado` — `GET /v1/pessoas/legado/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID atual ou legado da pessoa |

> `pessoa legado` consome o **`uuid_legado`** (o uuid que `pessoa list`
> devolve nesse campo, e que `pessoa get` aninha em `pessoas_legado[].uuid`),
> não o `id_legado` inteiro do mesmo item.

<a id="pessoa-patch"></a>
## `pessoa update`, `pessoa patch` ✅ { #pessoa-update }

- `pessoa update` — `PUT /v1/pessoas/{id}`
- `pessoa patch` — `PATCH /v1/pessoas/{id}`

`PUT` substitui o cadastro inteiro; `PATCH` atualiza apenas os campos enviados.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID da pessoa |
| `--json` | **sim** | Objeto JSON da atualização |

`pessoa patch` responde **204 No Content** (renderizado como `[]`) e aplica
só o que foi enviado — confirme o resultado com `pessoa get`.

`pessoa update` é substituição de verdade: responde **200** com o cadastro
completo, mas exige o objeto inteiro. Para uma pessoa Física a API cobra,
um erro de cada vez, todos estes campos:

| Campo | Observação |
|---|---|
| `nome`, `tipo_pessoa`, `perfis` | como em `pessoa create` |
| `cpf` | **não** `documento` — o campo de leitura (`documento`) e o de escrita (`cpf`) têm nomes diferentes |
| `codigo`, `rg`, `data_nascimento`, `email`, `telefone_comercial`, `observacao` | strings vazias são rejeitadas |
| `inscricoes[]` | ex: `[{"indicador_inscricao_estadual":"NAO CONTRIBUINTE","inscricao_estadual":"","inscricao_municipal":"","inscricao_suframa":""}]` |
| `outros_contatos[]` | não pode ser vazio; cada item exige `telefone_celular` |
| `enderecos[]` | não pode ser vazio; `complemento` também é obrigatório |

> Por isso, para mexer em um ou dois campos use `pessoa patch`. `pessoa
> update` só compensa quando você já tem o cadastro completo em mãos.

<a id="pessoa-inativar"></a>
<a id="pessoa-excluir"></a>
## `pessoa ativar`, `pessoa inativar`, `pessoa excluir` ✅ { #pessoa-ativar }

- `pessoa ativar` — `POST /v1/pessoas/ativar`
- `pessoa inativar` — `POST /v1/pessoas/inativar`
- `pessoa excluir` — `POST /v1/pessoas/excluir`

O payload esperado pela API é `{"uuids":[...]}`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON com os IDs |

`ativar` e `inativar` respondem **200** com `{todos[], ativos[], inativos[]}`
— o balde que não se aplica à operação vem `null`. `excluir` responde
**204 No Content** (renderizado como `[]`) e a exclusão é permanente:
`pessoa get` passa a devolver 404.

> **Mas `excluir` pode simplesmente ser recusado.** Uma pessoa vinculada a
> qualquer lançamento devolve `400` com a lista de ids que sobraram: "Os IDs
> […] não podem ser excluídos, pois já foram removidos anteriormente ou estão
> vinculados a um lançamento (negociações, contratos, eventos financeiros ou
> faturas de serviços/produtos)". Note que a mensagem **funde dois casos** —
> "já excluído" e "ainda vinculado" —, então ela não diz qual dos dois
> aconteceu. Confirmado em 2026-08-19 com o fornecedor que a Captura criou:
> depois do `captura aceitar`, ele passou a ter evento financeiro e a
> exclusão parou de ser possível. Nesses casos o caminho é `pessoa inativar`,
> que continua funcionando.

## `pessoa conta-conectada` ✅ { #pessoa-conta-conectada }

`GET /v1/pessoas/conta-conectada` — retorna os dados da empresa vinculada ao token.

Sem parâmetros. Retorna `{id_empresa, documento, razao_social, nome_fantasia, data_fundacao, email}`.
