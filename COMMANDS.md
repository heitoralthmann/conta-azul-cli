# Referência de comandos

Documentação canônica da superfície do `ca`: todos os comandos, agrupados pelo endpoint que consomem, com seus parâmetros.

**Este arquivo é a fonte de verdade sobre o que o CLI faz.** Ao alterar a integração — endpoint novo, filtro novo, comando removido — atualize-o no mesmo commit. Se ele divergir do código, ele deixa de servir ao propósito.

Convenção de leitura: `obrig.` marca o que falha sem valor; `padrão` é o que o CLI assume quando você omite.

Cada endpoint traz uma marca de confiança:

- **✅ verificado** — chamado contra a API real e respondeu 200 (2026-08-15)
- **⚠️ não verificado** — path correto conforme a documentação, mas nunca exercitado; exige disparar escrita real

---

## Referência rápida

| Comando | Endpoint | |
|---|---|---|
| `auth login` | OAuth2 (navegador) | ✅ |
| `auth logout` | — (local) | ✅ |
| `categoria list` | `GET /v1/categorias` | ✅ |
| `centro-de-custo list` | `GET /v1/centro-de-custo` | ✅ |
| `conta-financeira list` | `GET /v1/conta-financeira` | ✅ |
| `conta-financeira saldo` | `GET /v1/conta-financeira/{id}/saldo-atual` | ✅ |
| `conta-a-receber list` | `GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar` | ✅ |
| `conta-a-receber create` | `POST /v1/financeiro/eventos-financeiros/contas-a-receber` | ⚠️ |
| `conta-a-pagar list` | `GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar` | ✅ |
| `conta-a-pagar create` | `POST /v1/financeiro/eventos-financeiros/contas-a-pagar` | ⚠️ |
| `parcela get` | `GET /v1/financeiro/eventos-financeiros/parcelas/{id}` | ✅ |
| `parcela baixar` | `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` | ⚠️ |
| `financeiro alteracoes` | `GET /v1/financeiro/eventos-financeiros/alteracoes` | ✅ |
| `protocolo get` | `GET /v1/protocolo/{id}` | ⚠️ |

---

## Contrato de saída

Vale para **todos** os comandos:

| Canal | Conteúdo |
|---|---|
| `stdout` | Só o payload JSON compacto. Nada mais — seguro para `\| jq`. |
| `stderr` | Envelopes JSON de erro e de aviso. |
| exit code | `0` sucesso, `1` falha. Binário. |

**Envelope de erro** (stderr, exit 1):

```json
{"kind":"client_error","retryable":false,"http_status":404,"protocol_id":null,"correlation_id":"a1b2…","message":"…"}
```

**Envelope de aviso** (stderr, exit 0 — a operação teve sucesso):

```json
{"kind":"warning","message":"Intervalo de vencimento não informado por completo; usando 2026-08-01 a 2026-08-31. …"}
```

### Valores de `kind`

| `kind` | Significado | `retryable` |
|---|---|---|
| `client_error` | Requisição inválida (4xx). Corrija os argumentos. | `false` |
| `auth_failed` | Falha de autenticação. Rode `ca auth login`. | `false` |
| `rate_limited` | Limite de requisições atingido. | `true` |
| `transient` | Erro de rede ou 5xx transitório. | `true` |
| `server_error` | Erro do servidor não recuperável. | `false` |
| `ambiguous` | Resposta perdida após envio. **A escrita pode ter sido aplicada** — reconcilie via `financeiro alteracoes`. | `false` |
| `poll_timeout_known_id` | Escrita aceita, polling estourou. Retome com `protocolo get <protocol_id>`. | `false` |
| `poll_drop_known_id` | Polling interrompido. Retome com `protocolo get <protocol_id>`. | `false` |

Quando `kind` é `ambiguous`, `poll_timeout_known_id` ou `poll_drop_known_id`, **não reenvie a escrita cegamente**: o CLI não deduplica. Use o `protocol_id` do envelope.

## Opções globais

Aceitas por qualquer comando:

| Opção | Efeito |
|---|---|
| `--debug` | Grava log estruturado em `~/.cache/conta-azul-cli/log.jsonl`. Credenciais são redigidas. |
| `-q, --quiet` | Suprime tudo exceto erros. |
| `-h, --help` | Ajuda do comando. |
| `-V, --version` | Versão do CLI. |
| `-n, --no-interaction` | Não faz perguntas interativas. |

## Paginação

Onde há `--pagina` / `--tamanho-pagina`:

| Parâmetro | Padrão | Valores aceitos |
|---|---|---|
| `--pagina` | `1` | Qualquer inteiro positivo |
| `--tamanho-pagina` | `50` | **`10`, `20`, `50`, `100`, `200`, `500`, `1000`** — qualquer outro é rejeitado localmente, sem chamar a API |

A resposta traz `itens_totais` para você saber quantas páginas percorrer.

## Datas

Dois formatos, e eles não são intercambiáveis:

| Contexto | Formato | Exemplo |
|---|---|---|
| Vencimento e baixa | `YYYY-MM-DD` | `2026-08-01` |
| `financeiro alteracoes` | ISO 8601 **sem timezone** | `2026-08-01T00:00:00` |

> Em `alteracoes`, sufixo `Z` ou offset (`-03:00`) faz a API responder **400**.

Onde há intervalo de datas, a API o **exige**. Omitir as opções não causa erro: o CLI assume o **mês corrente** e avisa em stderr qual recorte aplicou. Informar ambas as opções silencia o aviso.

---

## Autenticação

### `auth login` ✅

Sem parâmetros. Imprime uma URL, sobe um listener HTTPS local na porta **9876** e aguarda o redirect. Tokens vão para `~/.config/conta-azul-cli/tokens.json` com permissão `0600`.

O refresh é automático e invisível — não existe comando para isso. Renovação preventiva a menos de 60 s da expiração, e reativa uma vez em caso de `401`.

### `auth logout` ✅

Sem parâmetros. Remove as credenciais locais.

---

## Categorias

### `categoria list` ✅

`GET /v1/categorias`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Retorna `{itens_totais, itens[]}`. Cada item traz `id`, `nome`, `tipo` (`RECEITA`/`DESPESA`), `categoria_pai` e `entrada_dre`.

---

## Centros de custo

### `centro-de-custo list` ✅

`GET /v1/centro-de-custo` — note o **singular** no path.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Cada item traz `id`, `codigo`, `nome`, `ativo`.

---

## Contas financeiras

### `conta-financeira list` ✅

`GET /v1/conta-financeira` — **singular**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Cada item traz `id`, `nome`, `banco`, `tipo`, `ativo`, `conta_padrao`.

### `conta-financeira saldo` ✅

`GET /v1/conta-financeira/{id}/saldo-atual`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--id` | **sim** | ID da conta financeira (de `conta-financeira list`) |

Retorna `{"saldo_atual": 5931.64}`. É opção `--id`, não argumento posicional.

---

## Contas a receber

### `conta-a-receber list` ✅

`GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-vencimento-de` | não¹ | 1º dia do mês corrente | Vencimento inicial (`YYYY-MM-DD`) |
| `--data-vencimento-ate` | não¹ | último dia do mês corrente | Vencimento final (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

> **O `id` de cada item é o da PARCELA, não do evento financeiro.** É esse valor que `parcela get` e `parcela baixar` consomem. O id do evento vem aninhado em `evento.id` na resposta de `parcela get`.

Cada item traz `id`, `status` (`ACQUITTED`, `OVERDUE`, …), `status_traduzido`, `total`, `pago`, `nao_pago`, `data_vencimento`, `data_competencia`, `descricao`, `categorias[]`, `centros_de_custo[]`, `cliente`. A resposta ainda inclui um bloco `totais` com somatórios por situação.

### `conta-a-receber create` ⚠️

`POST /v1/financeiro/eventos-financeiros/contas-a-receber` — **escrita assíncrona**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--json` | **sim** | — | Payload JSON, repassado à API **verbatim** |
| `--poll-timeout` | não | `60` | Segundos aguardando a confirmação assíncrona |
| `--no-wait` | não | — | Retorna o `protocol_id` na hora, sem aguardar |

O CLI não valida o conteúdo de `--json`; o schema é o da API. A resposta é um protocolo que o CLI acompanha por polling, salvo com `--no-wait`.

---

## Contas a pagar

### `conta-a-pagar list` ✅

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

### `conta-a-pagar create` ⚠️

`POST /v1/financeiro/eventos-financeiros/contas-a-pagar` — **escrita assíncrona**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--json` | **sim** | — | Payload JSON, repassado à API **verbatim** |
| `--poll-timeout` | não | `60` | Segundos aguardando a confirmação assíncrona |
| `--no-wait` | não | — | Retorna o `protocol_id` na hora, sem aguardar |

---

## Parcelas

### `parcela get` ✅

`GET /v1/financeiro/eventos-financeiros/parcelas/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. ID da parcela — o `id` devolvido pelas buscas de contas |

Retorna a parcela com o evento financeiro aninhado em `evento`, incluindo `evento.id`, `condicao_pagamento` e `rateio[]`.

### `parcela baixar` ⚠️

`PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` — **escrita assíncrona**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `<id>` | **sim** | — | Argumento posicional. ID da parcela |
| `--valor` | **sim** | — | Valor da baixa (ex: `100.50`) |
| `--data` | **sim** | — | Data da baixa (`YYYY-MM-DD`) |
| `--poll-timeout` | não | `60` | Segundos aguardando confirmação |
| `--no-wait` | não | — | Retorna o `protocol_id` na hora |

> Não existe subrecurso `/baixar` na API. A baixa é um `PATCH` na própria parcela.

---

## Eventos financeiros

### `financeiro alteracoes` ✅

`GET /v1/financeiro/eventos-financeiros/alteracoes`

Feed de alterações no período — o caminho para reconciliar escritas que terminaram em `ambiguous`.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | início do mês corrente | ISO 8601 **sem timezone** |
| `--data-fim` | não¹ | fim do mês corrente | ISO 8601 **sem timezone** |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna `{itens_totais, itens[]}`, onde cada item traz apenas o `id` do evento alterado. Use `parcela get` para hidratar.

---

## Protocolos

### `protocolo get` ⚠️

`GET /v1/protocolo/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `protocol_id` devolvido por uma escrita |

Consulta o status de uma escrita assíncrona. Não tem opções próprias. É como se retoma uma escrita disparada com no-wait, ou uma que terminou em `poll_timeout_known_id` ou `poll_drop_known_id`.

---

## Fora do escopo do CLI

Endpoints que **existem e respondem**, mas ainda não têm comando:

| Endpoint | O que faz |
|---|---|
| `GET /v1/financeiro/transferencias` | Lista transferências. Exige `data_inicio` e `data_fim`. |
| `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas` | Parcelas de um evento financeiro. |
| `GET /v1/financeiro/categorias-dre` | Categorias DRE. |
| `GET /v1/categorias/configuracao-padrao` | De-para padrão de categorias. |
| `GET /v1/financeiro/eventos-financeiros/saldo-inicial` | Saldo inicial no período. |

Recursos que **não existem** na API v1 — não procure o comando, não há endpoint:

**lançamentos**, **cobranças**, busca por id de conta a pagar/receber, update/delete desses eventos, e criação de transferência.

> Comandos para esses recursos já existiram, apontando para paths inventados, e retornavam 404. Foram removidos em vez de continuarem anunciando o que não funciona.

---

## Notas para quem for estender

Duas armadilhas de nomenclatura, confirmadas em produção e responsáveis por praticamente todos os 404 da versão anterior:

1. **O segmento `/financeiro/` só existe em parte dos recursos.** Categorias, centros de custo e contas financeiras ficam na raiz da `v1`.
2. **A nomenclatura alterna plural e singular:** `categorias`, mas `centro-de-custo` e `conta-financeira`.

Nunca deduza um path da documentação sem exercitá-lo. `tests/Unit/Api/FinanceiroClientTest.php` trava cada path, o verbo da baixa e os nomes dos parâmetros de data — estenda-o junto com qualquer endpoint novo.

Lista autoritativa de operações: https://developers.contaazul.com/docs/financial-apis-openapi/v1 — o portal bloqueia `curl` e fetch automatizado (403), então abra no navegador.

Ver também: [`README.md`](README.md) para instalação, configuração e OAuth; [`docs/financial-apis-openapi.yaml`](docs/financial-apis-openapi.yaml) para o inventário de endpoints usado na detecção de drift.
