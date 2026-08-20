<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Vendas

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

**Verificado contra produção em 2026-08-19** — os nove comandos, incluindo o
ciclo completo de escrita (criar, atualizar e excluir três vendas de teste).
`venda list` foi o primeiro grupo em que **todos** os filtros documentados
existiam de verdade: os oito passaram na receita de baseline + `zzz_bogus` +
valor discriminante. As armadilhas do grupo estão na escrita, não na leitura.

## `venda list` ✅ { #venda-list }

`GET /v1/venda/busca`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| `--campo-ordenado-ascendente` | não | — | Filtro campo_ordenado_ascendente |
| `--campo-ordenado-descendente` | não | — | Filtro campo_ordenado_descendente |
| `--data-criacao-ate` | não | — | Filtro data_criacao_ate |
| `--data-criacao-de` | não | — | Filtro data_criacao_de |
| `--data-fim` | não | — | Filtro data_fim |
| `--data-inicio` | não | — | Filtro data_inicio |
| `--termo-busca` | não | — | Filtro termo_busca |
| `--totais` | não | — | Filtro totais |

Diferente de `contrato list`, o intervalo de datas é opcional — a API não o exige. Retorna `{totais, quantidades, total_itens, itens[]}`.

Detalhes confirmados exercitando:

- `--termo-busca` casa **nome do cliente e número da venda**, não as
  observações. Buscar por `TESTE HEITOR` (o texto em `observacoes` das
  vendas de teste) devolveu `0`; buscar pelo número devolveu exatamente 1.
- `--data-inicio`/`--data-fim` filtram a **data da venda**;
  `--data-criacao-de`/`--data-criacao-ate` filtram a data de criação. São
  intervalos distintos e o mesmo par de datas deu totais diferentes (27 vs 19).
- `--totais` é um **filtro de situação**, apesar do nome. `--totais CANCELED`
  reduziu 6652 para 1. Valor fora do enum devolve `400` — é dos poucos
  parâmetros que a API valida em vez de descartar.
- `--campo-ordenado-*` não muda a contagem (é ordenação), mas um valor
  inválido devolve `400` com a lista de valores aceitos — foi assim que se
  provou que o parâmetro é reconhecido.
- Aceita `--tamanho-pagina 1000`: **não** é afetado pelo limite de 100 que
  atinge `servico list` e as notas fiscais.

## `venda create` ✅ { #venda-create }

`POST /v1/venda` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON da venda (ver campos obrigatórios abaixo) |

A documentação lista cinco campos obrigatórios; a API cobra **seis**, e
`condicao_pagamento` não estava na lista. Descobertos um a um, cada `400`
revelando só o próximo:

| Campo | Formato |
|---|---|
| `id_cliente` | uuid da pessoa |
| `numero` | inteiro (use `venda proximo-numero`) |
| `situacao` | `EM_ANDAMENTO` ou `APROVADO` — nada mais |
| `data_venda` | `YYYY-MM-DD` |
| `itens` | array de `{id, quantidade, valor}` — `id` é o uuid do produto ou serviço, **não** `id_item` |
| `condicao_pagamento` | `{opcao_condicao_pagamento, parcelas[]}` |

`opcao_condicao_pagamento` aceita `À vista` (acentuado e capitalizado assim),
`Nx` (`1x`, `12x`) ou dias separados por vírgula (`30`, `30,60`, `15,30,45`).
Cada parcela é `{data_vencimento, valor}`.

Payload mínimo que passou:

```json
{
  "id_cliente": "11111111-1111-4111-8111-111111111111",
  "numero": 7297,
  "situacao": "EM_ANDAMENTO",
  "data_venda": "2026-08-19",
  "observacoes": "TESTE HEITOR",
  "itens": [{ "id": "22222222-2222-4222-8222-222222222222", "quantidade": 1, "valor": 10 }],
  "condicao_pagamento": {
    "opcao_condicao_pagamento": "À vista",
    "parcelas": [{ "data_vencimento": "2026-08-19", "valor": 10 }]
  }
}
```

**A resposta do `POST` e a do `GET` falam enums diferentes.** O `create`
devolve `situacao.nome` em inglês (`IN_PROCESS`) e chama o campo de
`pendencia`; o `get` devolve `EM_ANDAMENTO` e `tipo_pendencia` para a mesma
venda. Não deduza o vocabulário de um a partir do outro.

Retorna `{id, id_legado, numero, origem, data_venda, situacao, pendencia,
valor_composicao, condicao_pagamento, …}` — o `id` é o uuid a usar nos
demais comandos.

## `venda get` ✅ { #venda-get }

`GET /v1/venda/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid ou id legado da venda |

Aceita os dois ids (confirmado com uuid e com `id_legado`). A resposta é um
**envelope**, não a venda solta: `{evento_financeiro, notificacao,
natureza_operacao, contrato, cliente, vendedor, venda}` — os campos da venda
ficam sob a chave `venda`.

**`get` não devolve os itens.** `venda.total_itens` é um objeto de contagens
(`{contagem_produtos, contagem_servicos, contagem_nao_conciliados}`), não uma
lista nem um número. Para os itens, use `venda itens`.

## `venda update` ✅ { #venda-update }

`PUT /v1/venda/{id}` — **escrita síncrona**; a API não expõe `PATCH` para vendas, então o payload precisa trazer o objeto completo, incluindo `versao`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da venda — id legado devolve `400` |
| `--json` | **sim** | Payload JSON completo da venda |

Mesmos campos obrigatórios do `create`, mais `versao`. E aqui está a
armadilha mais desagradável do grupo:

> **`versao` precisa ser diferente de zero.** Uma venda recém-criada nasce
> com `versao: 0`; devolver esse valor no `PUT` responde
> `O campo 'versao' é obrigatório` — o validador não distingue zero de
> ausente. Mandar `1` funciona.

E `versao` **não é trava otimista**: o valor enviado é ignorado. Com a venda
em `versao: 1`, tanto `1` quanto `99` foram aceitos e o servidor apenas
incrementou o próprio contador (1 → 2 → 3). Ou seja, o campo é obrigatório e
inútil — mande qualquer inteiro positivo.

Retorna só `{id, id_legado}`.

## `venda imprimir` ✅ { #venda-imprimir }

`GET /v1/venda/{id}/imprimir`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid ou id legado da venda |

A resposta da API é um PDF binário, não JSON. Para manter o contrato de stdout do CLI, o comando devolve `{"content_base64", "content_type"}` — decodifique `content_base64` para obter os bytes originais. Confirmado: `content_type` é `application/pdf` e os bytes decodificados começam com `%PDF-1.5`.

## `venda itens` ✅ { #venda-itens }

`GET /v1/venda/{id_venda}/itens`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `<id-venda>` | **sim** | — | Argumento posicional. Uuid da venda |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página |

Retorna `{itens[], itens_totais, totais}`.

Só aceita uuid: passar o `id_legado` devolve `400` ("o valor informado para o
ID precisa ser do tipo UUID") — mesmo id que `get` e `imprimir` aceitam sem
reclamar. Cada item traz `{id, id_item, nome, descricao, tipo, quantidade,
valor, custo, id_centro_custo}`, onde `id` é o id da linha da venda e
`id_item` é o uuid do produto ou serviço. Aceita `--tamanho-pagina 1000`.

## `venda vendedores` ✅ { #venda-vendedores }

`GET /v1/venda/vendedores`

Sem parâmetros e sem paginação: devolve o array completo de vendedores cadastrados. Cada item traz apenas `{id, nome}` — o `id_legado` que a documentação promete **não vem na resposta**.

## `venda proximo-numero` ✅ { #venda-proximo-numero }

`GET /v1/venda/proximo-numero`

Sem parâmetros. Retorna o próximo número de venda disponível como um inteiro solto (ou `null`), não um objeto — mesmo formato de `contrato proximo-numero`. O contador **volta atrás** quando as vendas são excluídas: passou de 7297 para 7298 assim que a venda 7297 foi criada e voltou a 7297 depois que as três vendas de teste foram removidas.

## `venda excluir-lote` ✅ { #venda-excluir-lote }

`POST /v1/venda/exclusao-lote` — exclui vendas em lote.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON com `{"ids": [...]}` — de 1 a 10 uuids por chamada |

Retorna `{atualizados, ignorados}` com `200` (não `204`).

Os dois limites são cobrados de verdade: `[]` devolve `400`
("deve conter ao menos 1 item") e 11 ids devolvem `400`
("não pode conter mais de 10 itens"). Um uuid inexistente **não** é erro —
entra em `ignorados` e a chamada responde `200`, então confira o contador em
vez de confiar no status.

**A exclusão é lógica**, como em `servico` e ao contrário de `produto`:
depois do `exclusao-lote`, `venda get` e `venda itens` continuam
respondendo `200` com o registro completo. O que muda é `venda.status`, que
passa a `CANCELADO` — enquanto `venda.situacao` **continua** `EM_ANDAMENTO`,
que é o par de campos que engana. A prova confiável é o sumiço da listagem.
