# Referência de comandos

Documentação canônica da superfície do `ca`: todos os comandos, agrupados pelo endpoint que consomem, com seus parâmetros.

**Este arquivo é a fonte de verdade sobre o que o CLI faz.** Ao alterar a integração — endpoint novo, filtro novo, comando removido — atualize-o no mesmo commit. Se ele divergir do código, ele deixa de servir ao propósito.

Convenção de leitura: `obrig.` marca o que falha sem valor; `padrão` é o que o CLI assume quando você omite.

Cada endpoint traz uma marca de confiança:

- **✅ verificado** — exercitado contra a API real e respondeu como documentado. As leituras financeiras foram verificadas em 2026-08-15; os grupos `pessoa`, `produto` e `servico` tiveram o CRUD completo (criar, ler, atualizar, excluir) exercitado em produção em 2026-08-19
- **⚠️ não verificado** — escrito a partir da documentação e **nunca exercitado**. Não leia isso como "provavelmente certo": leia como **não confiável**

> ⚠️ **O que `⚠️` realmente significa, depois da campanha de verificação de
> 2026-08-19.** A marca não cobre só "a escrita nunca foi disparada". Dos
> três grupos verificados até agora, **dois tinham filtros que não
> filtravam nada** — `produto list --codigo` mandava `codigo` onde a API
> quer `sku`, e `servico list` expunha quatro filtros dos quais só um
> existia, sob outro nome. Como as listagens **respondem 200 e descartam em
> silêncio** parâmetro desconhecido (ver "Notas para quem for estender"),
> esses comandos não davam erro: devolviam a lista inteira como se tudo
> casasse.
>
> Ou seja: num comando `⚠️`, desconfie de **tudo** — path, nome de filtro,
> nome de campo do payload, tipo do id e formato da resposta. Os filtros
> listados nas seções `⚠️` abaixo saíram da documentação, não de uma
> chamada real, e a taxa de acerto observada até agora foi de 2 em 3
> grupos com pelo menos um erro.

---

## Referência rápida

| Comando | Endpoint | |
|---|---|---|
| `auth login` | OAuth2 (navegador) | ✅ |
| `auth logout` | — (local) | ✅ |
| `categoria list` | `GET /v1/categorias` | ✅ |
| `categoria configuracao-padrao` | `GET /v1/categorias/configuracao-padrao` | ✅ |
| `categoria dre` | `GET /v1/financeiro/categorias-dre` | ✅ |
| `centro-de-custo list` | `GET /v1/centro-de-custo` | ✅ |
| `centro-de-custo create` | `POST /v1/centro-de-custo` | ⚠️ |
| `conta-financeira list` | `GET /v1/conta-financeira` | ✅ |
| `conta-financeira saldo` | `GET /v1/conta-financeira/{id}/saldo-atual` | ✅ |
| `transferencia list` | `GET /v1/financeiro/transferencias` | ✅ |
| `conta-a-receber list` | `GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar` | ✅ |
| `conta-a-receber create` | `POST /v1/financeiro/eventos-financeiros/contas-a-receber` | ⚠️ |
| `cobranca create` | `POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca` | ⚠️ |
| `cobranca get` | `GET /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` | ⚠️ |
| `cobranca delete` | `DELETE /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` | ⚠️ |
| `conta-a-pagar list` | `GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar` | ✅ |
| `conta-a-pagar create` | `POST /v1/financeiro/eventos-financeiros/contas-a-pagar` | ⚠️ |
| `parcela get` | `GET /v1/financeiro/eventos-financeiros/parcelas/{id}` | ✅ |
| `parcela baixar` | `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` | ⚠️ |
| `parcela list` | `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas` | ⚠️ |
| `baixa create` | `POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` | ⚠️ |
| `baixa list` | `GET /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` | ⚠️ |
| `baixa get` | `GET /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ⚠️ |
| `baixa update` | `PATCH /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ⚠️ |
| `baixa delete` | `DELETE /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ⚠️ |
| `financeiro alteracoes` | `GET /v1/financeiro/eventos-financeiros/alteracoes` | ✅ |
| `financeiro saldo-inicial` | `GET /v1/financeiro/eventos-financeiros/saldo-inicial` | ⚠️ |
| `protocolo get` | `GET /v1/protocolo/{id}` | ⚠️ |
| `contrato list` | `GET /v1/contratos` | ⚠️ |
| `contrato create` | `POST /v1/contratos` | ⚠️ |
| `contrato proximo-numero` | `GET /v1/contratos/proximo-numero` | ⚠️ |
| `contrato get` | `GET /v1/contratos/{id}` | ⚠️ |
| `contrato delete` | `DELETE /v1/contratos/{id}` | ⚠️ |
| `contrato encerrar` | `POST /v1/contratos/{id}/encerrar` | ⚠️ |
| `pessoa list` | `GET /v1/pessoas` | ✅ |
| `pessoa create` | `POST /v1/pessoas` | ✅ |
| `pessoa get` | `GET /v1/pessoas/{id}` | ✅ |
| `pessoa update` | `PUT /v1/pessoas/{id}` | ✅ |
| `pessoa patch` | `PATCH /v1/pessoas/{id}` | ✅ |
| `pessoa legado` | `GET /v1/pessoas/legado/{id}` | ✅ |
| `pessoa ativar` | `POST /v1/pessoas/ativar` | ✅ |
| `pessoa inativar` | `POST /v1/pessoas/inativar` | ✅ |
| `pessoa excluir` | `POST /v1/pessoas/excluir` | ✅ |
| `pessoa conta-conectada` | `GET /v1/pessoas/conta-conectada` | ✅ |
| `produto list` | `GET /v1/produtos` | ✅ |
| `produto create` | `POST /v1/produtos` | ✅ |
| `produto get` | `GET /v1/produtos/{id}` | ✅ |
| `produto update` | `PATCH /v1/produtos/{id}` | ✅ |
| `produto delete` | `DELETE /v1/produtos/{id}` | ✅ |
| `produto categorias` | `GET /v1/produtos/categorias` | ✅ |
| `produto cest` | `GET /v1/produtos/cest` | ✅ |
| `produto ncm` | `GET /v1/produtos/ncm` | ✅ |
| `produto unidades-medida` | `GET /v1/produtos/unidades-medida` | ✅ |
| `produto ecommerce-categorias` | `GET /v1/produtos/ecommerce-categorias` | ⚠️¹ |
| `produto ecommerce-marcas` | `GET /v1/produtos/ecommerce-marcas` | ✅ |
| `servico list` | `GET /v1/servicos` | ✅ |
| `servico create` | `POST /v1/servicos` | ✅ |
| `servico get` | `GET /v1/servicos/{id}` | ✅ |
| `servico update` | `PATCH /v1/servicos/{id}` | ✅ |
| `servico delete` | `DELETE /v1/servicos` | ✅ |
| `nota-fiscal list` | `GET /v1/notas-fiscais` | ⚠️ |
| `nota-fiscal get` | `GET /v1/notas-fiscais/{chave}` | ⚠️ |
| `nota-fiscal vincular-mdfe` | `POST /v1/notas-fiscais/vinculo-mdfe` | ⚠️ |
| `nota-fiscal-servico list` | `GET /v1/notas-fiscais-servico` | ⚠️ |
| `venda list` | `GET /v1/venda/busca` | ⚠️ |
| `venda create` | `POST /v1/venda` | ⚠️ |
| `venda get` | `GET /v1/venda/{id}` | ⚠️ |
| `venda update` | `PUT /v1/venda/{id}` | ⚠️ |
| `venda imprimir` | `GET /v1/venda/{id}/imprimir` | ⚠️ |
| `venda itens` | `GET /v1/venda/{id_venda}/itens` | ⚠️ |
| `venda vendedores` | `GET /v1/venda/vendedores` | ⚠️ |
| `venda proximo-numero` | `GET /v1/venda/proximo-numero` | ⚠️ |
| `venda excluir-lote` | `POST /v1/venda/exclusao-lote` | ⚠️ |
| `orcamento list` | `GET /v1/orcamentos` | ⚠️ |
| `orcamento create` | `POST /v1/orcamentos` | ⚠️ |
| `orcamento get` | `GET /v1/orcamentos/{id}` | ⚠️ |
| `orcamento excluir-lote` | `DELETE /v1/orcamentos` | ⚠️ |
| `captura enviar` | `POST /v1/captura/documentos` | ⚠️ |
| `captura status` | `GET /v1/captura/documentos/status` | ⚠️ |
| `captura get` | `GET /v1/captura/{id}` | ⚠️ |
| `captura aceitar` | `POST /v1/captura/{id}` | ⚠️ |
| `captura recusar` | `DELETE /v1/captura/{id}` | ⚠️ |

¹ `produto ecommerce-categorias` foi exercitado e **respondeu 400 em todas as tentativas**, inclusive sem nenhum parâmetro. Ver a seção de Produtos.

---

## Contrato de saída

Vale para **todos** os comandos:

| Canal | Conteúdo |
|---|---|
| `stdout` | Só o payload JSON compacto. Nada mais — seguro para `\| jq`. |
| `stderr` | Envelopes JSON de erro e de aviso. |
| exit code | `0` sucesso, `1` falha. Binário. |

> **Resposta sem corpo sai como `[]`, não `{}`.** Um `204 No Content` — e
> também um `{}` vindo da API — é decodificado para um array PHP vazio, que
> volta a ser serializado como `[]`. Quem consome com `jq` deve tratar
> `[]` como "nenhum conteúdo" nos comandos marcados `204` (`pessoa patch`,
> `pessoa excluir`, `contrato delete`, `orcamento excluir-lote`,
> `captura recusar`, `nota-fiscal vincular-mdfe`).

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

> **A validação local é mais permissiva que alguns endpoints.** O CLI aceita
> até `1000`, mas `servico list`, `nota-fiscal list` e
> `nota-fiscal-servico list` só admitem `10`, `20`, `50` ou `100` — passar
> `200` ou mais neles passa pela validação local e volta 400 da API.

E o nome do campo de contagem **não é o mesmo em todo lugar**:

| Formato | Onde |
|---|---|
| `{itens_totais, itens[]}` | comandos financeiros |
| `{totalItems, items[]}` | `pessoa list`, `produto list` |
| `{total_items, items[]}` | catálogos de `produto` (categorias, cest, ncm, …) |
| `{itens[], paginacao{total_itens}}` | `servico list` |

Os grupos ainda não verificados podem trazer outras variações — `contrato
list` está documentado como `{itens_totais, items[]}` e `venda list` como
`{totais, quantidades, total_itens, itens[]}`, mas nenhum dos dois foi
exercitado ainda.

## Datas

Dois formatos, e eles não são intercambiáveis:

| Contexto | Formato | Exemplo |
|---|---|---|
| Vencimento e baixa | `YYYY-MM-DD` | `2026-08-01` |
| `financeiro alteracoes` | ISO 8601 **sem timezone** | `2026-08-01T00:00:00` |

> Em `alteracoes`, sufixo `Z` ou offset (`-03:00`) faz a API responder **400**.

Onde há intervalo de datas, a API o **exige**. Omitir as opções não causa erro: o CLI assume o **mês corrente** e avisa em stderr qual recorte aplicou. Informar ambas as opções silencia o aviso. Exceção: `nota-fiscal-servico list`, cujo intervalo é limitado a 15 dias pela API — o default ali são os **últimos 15 dias**, não o mês corrente.

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

### `categoria configuracao-padrao` ✅

`GET /v1/categorias/configuracao-padrao`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--sugestao-padrao` / `--no-sugestao-padrao` | não | `--sugestao-padrao` | Inclui (ou omite) a sugestão padrão de categoria em cada item |

Retorna uma lista de de-para entre operação financeira (`tipo_operacao`, ex: `FRETES_RECEBIDOS`, `JUROS_PAGOS`) e a categoria configurada para ela (`id_categoria`, `nome_categoria`). Com `--no-sugestao-padrao`, o campo `sugestao_padrao` de cada item vem `null`.

### `categoria dre` ✅

`GET /v1/financeiro/categorias-dre`

Sem parâmetros. Retorna `{itens[]}` com a estrutura hierárquica da DRE (Demonstração do Resultado do Exercício): cada item traz `descricao`, `codigo`, `subitens[]` e `categorias_financeiras[]` associadas.

---

## Centros de custo

### `centro-de-custo list` ✅

`GET /v1/centro-de-custo` — note o **singular** no path.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Cada item traz `id`, `codigo`, `nome`, `ativo`.

### `centro-de-custo create` ⚠️

`POST /v1/centro-de-custo` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do centro de custo (`nome` obrigatório; `codigo` opcional) |

Retorna `{id, codigo, nome, ativo}` do centro de custo criado.

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

## Transferências

### `transferencia list` ✅

`GET /v1/financeiro/transferencias`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | 1º dia do mês corrente | Início do intervalo (`YYYY-MM-DD`) |
| `--data-fim` | não¹ | último dia do mês corrente | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

> As datas vão em `YYYY-MM-DD` puro, diferente de `financeiro alteracoes` (mesmo domínio `/financeiro/`), que exige o instante ISO 8601 completo.

Cada item traz `id`, `descricao`, `valor`, `data`, e os blocos `origem`/`destino` com `data`, `composicao_valor` (`valor_bruto`, `juros`, `multa`, `valor_liquido`, `desconto`, `taxa`) e `conta_financeira` (`id`, `nome`, `instituicao_bancaria`).

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

## Cobranças

Gera cobrança (boleto, PIX ou link de pagamento) para a parcela de uma conta a receber. Spec OpenAPI próprio (`charge-apis-openapi`), separado do núcleo Financeiro.

### `cobranca create` ⚠️

`POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON da cobrança (`conta_bancaria`, `descricao_fatura`, `id_parcela`, `data_vencimento` e `tipo` — `LINK_PAGAMENTO`, `PIX_COBRANCA` ou `BOLETO` — obrigatórios) |

O CLI não valida o conteúdo de `--json`; o schema é o da API. Retorna `{id, url, status}`.

### `cobranca get` ⚠️

`GET /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da cobrança |

### `cobranca delete` ⚠️

`DELETE /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` — recomendado só para cobrança gerada incorretamente ou a invalidar antes do pagamento.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da cobrança |

A documentação da API lista resposta `200 OK` sem schema de corpo para este endpoint — diferente da convenção `204 No Content` do resto do CLI. Comportamento real ainda não verificado contra a API.

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

### `parcela list` ⚠️

`GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id-evento>` | **sim** | Argumento posicional. ID do evento financeiro (`evento.id` aninhado na resposta de `parcela get`) |

Sem paginação: a API devolve o array completo de parcelas do evento. Cada item tem o mesmo formato de `parcela get`.

---

## Baixas

Recurso dedicado de baixa (quitação) de uma parcela — mais rico que o `PATCH` direto de `parcela baixar`: registra data, valor, juros, multa, desconto e método de pagamento. Uma parcela pode ter mais de uma baixa (pagamento parcial). Spec OpenAPI próprio (`acquittance-apis-openapi`), separado do núcleo Financeiro.

### `baixa create` ⚠️

`POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |
| `--json` | **sim** | Payload JSON da baixa (`data_pagamento`, `conta_financeira` e `composicao_valor` — objeto com `valor_bruto` obrigatório e `multa`/`juros`/`desconto`/`taxa` opcionais — obrigatórios) |

O CLI não valida o conteúdo de `--json`; o schema é o da API.

### `baixa list` ⚠️

`GET /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |

Sem paginação: a API devolve o array completo de baixas da parcela.

### `baixa get` ⚠️

`GET /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |

### `baixa update` ⚠️

`PATCH /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |
| `--json` | **sim** | Payload JSON com as mudanças; campo `versao` (a versão atual da baixa) é obrigatório |

**Controle de concorrência otimista:** a API exige a `versao` atual no payload e a incrementa após o sucesso, para evitar que duas atualizações concorrentes se sobrescrevam silenciosamente.

### `baixa delete` ⚠️

`DELETE /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` — use com cautela: impacta o saldo e o histórico financeiro da parcela associada.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |

A documentação da API lista resposta `200 OK` sem schema de corpo para este endpoint — diferente da convenção `204 No Content` do resto do CLI (mesma observação de `cobranca delete`). Comportamento real ainda não verificado contra a API.

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

### `financeiro saldo-inicial` ⚠️

`GET /v1/financeiro/eventos-financeiros/saldo-inicial`

Saldos iniciais das contas financeiras no período.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | início do mês corrente | ISO 8601 **sem timezone** |
| `--data-fim` | não¹ | fim do mês corrente | ISO 8601 **sem timezone** |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna `{itens_totais, itens[]}`.

---

## Protocolos

### `protocolo get` ⚠️

`GET /v1/protocolo/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `protocol_id` devolvido por uma escrita |

Consulta o status de uma escrita assíncrona. Não tem opções próprias. É como se retoma uma escrita disparada com no-wait, ou uma que terminou em `poll_timeout_known_id` ou `poll_drop_known_id`.

---

## Contratos

### `contrato list` ⚠️

`GET /v1/contratos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | 1º dia do mês corrente | Início do intervalo (`YYYY-MM-DD`) |
| `--data-fim` | não¹ | último dia do mês corrente | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página |
| `--busca-textual` | não | — | Busca textual pelo nome do contrato |
| `--cliente-id` | não | — | Filtra pelo ID do cliente |
| `--campo-ordenado-ascendente` | não | — | `DATA_INICIO` ou `DATA_FIM`; se informado, ignora `--campo-ordenado-descendente` |
| `--campo-ordenado-descendente` | não | — | `DATA_INICIO` ou `DATA_FIM` |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna `{itens_totais, items[]}`.

### `contrato create` ⚠️

`POST /v1/contratos` — **escrita síncrona**, diferente das escritas financeiras: não devolve protocolo, o `id` do contrato já vem na resposta.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do contrato, repassado à API **verbatim** |

O CLI não valida o conteúdo de `--json`; o schema é o da API (`id_cliente`, `termos`, `condicao_pagamento` e `itens` são obrigatórios). Retorna `{id, id_legado, id_venda}`.

### `contrato proximo-numero` ⚠️

`GET /v1/contratos/proximo-numero`

Sem parâmetros. Retorna o próximo número de contrato disponível como um inteiro solto (ex: `4512645`), não um objeto — diferente de todos os outros comandos de leitura.

### `contrato get` ⚠️

`GET /v1/contratos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

### `contrato delete` ⚠️

`DELETE /v1/contratos/{id}` — exclusão **permanente**.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

Cancela todas as vendas associadas ao contrato (agendadas e efetivadas). Contratos em reajuste de valor não podem ser removidos. Resposta `204 No Content` — sem corpo.

### `contrato encerrar` ⚠️

`POST /v1/contratos/{id}/encerrar` — sem corpo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

Desativa o contrato: ele para de gerar novas cobranças, mas não é excluído (diferente de `contrato delete`). Contratos em reajuste de valor não podem ser encerrados. Resposta `204 No Content` — sem corpo.

---

## Pessoas / Fornecedores

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

> **Os enums vão acentuados e capitalizados como na interface**, não em
> `SCREAMING_SNAKE_CASE`: `tipo_pessoa` aceita `Física`, `Jurídica` ou
> `Estrangeira`; `tipo_perfil` aceita `Cliente`, `Fornecedor` ou
> `Transportadora`. Enviar `FISICA` ou `CLIENTE` devolve **400**.

### `pessoa list` ✅

`GET /v1/pessoas`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| filtros da API | não | — | `--busca`, `--ids`, `--documentos`, `--paises`, `--cidades`, `--ufs`, `--codigos-pessoa`, `--emails`, `--tipos-pessoa`, `--nomes`, `--telefones`, `--data-criacao-inicio`, `--data-criacao-fim`, `--data-alteracao-de`, `--data-alteracao-ate`, `--tipo-perfil`, `--tipo-ordenacao`, `--ordem-ordenacao`, `--com-endereco` |

Retorna `{totalItems, items[]}` — **camelCase e em inglês**, diferente do
`{itens_totais, itens[]}` dos comandos financeiros. Quando nada casa,
`items` vem `null`, não `[]`.

> **Os dois pares de data não usam o mesmo formato**, e trocá-los devolve 400:
>
> | Filtro | Formato | Exemplo |
> |---|---|---|
> | `--data-criacao-inicio` / `--data-criacao-fim` | `YYYY-MM-DD` | `2026-08-19` |
> | `--data-alteracao-de` / `--data-alteracao-ate` | ISO 8601 **sem timezone** | `2026-08-19T00:00:00` |

### `pessoa create` ✅

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

### `pessoa get` ✅, `pessoa legado` ✅

`GET /v1/pessoas/{id}` e `GET /v1/pessoas/legado/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID atual ou legado da pessoa |

> `pessoa legado` consome o **`uuid_legado`** (o uuid que `pessoa list`
> devolve nesse campo, e que `pessoa get` aninha em `pessoas_legado[].uuid`),
> não o `id_legado` inteiro do mesmo item.

### `pessoa update` ✅ e `pessoa patch` ✅

`PUT /v1/pessoas/{id}` substitui o cadastro; `PATCH /v1/pessoas/{id}` atualiza apenas os campos enviados.

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

### `pessoa ativar` ✅, `pessoa inativar` ✅ e `pessoa excluir` ✅

`POST /v1/pessoas/ativar`, `/inativar` e `/excluir`. O payload esperado pela API é `{"uuids":[...]}`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON com os IDs |

`ativar` e `inativar` respondem **200** com `{todos[], ativos[], inativos[]}`
— o balde que não se aplica à operação vem `null`. `excluir` responde
**204 No Content** (renderizado como `[]`) e a exclusão é permanente:
`pessoa get` passa a devolver 404.

### `pessoa conta-conectada` ✅

`GET /v1/pessoas/conta-conectada` — retorna os dados da empresa vinculada ao token.

Sem parâmetros. Retorna `{id_empresa, documento, razao_social, nome_fantasia, data_fundacao, email}`.

---

## Produtos

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

> **`GET /v1/produtos` ignora em silêncio todo parâmetro que não reconhece.**
> Um filtro com o nome errado não vira 400: vira `200` com o catálogo
> inteiro, como se tudo casasse. Por isso os filtros abaixo são só os que
> foram confirmados contra a produção — `--ids` e `--categoria-id`
> existiram até 2026-08-19 e foram removidos por não filtrarem nada.

### `produto list` ✅

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

### `produto create` ✅

`POST /v1/produtos`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Objeto JSON do produto |

**Só `nome` é obrigatório** — `{"nome":"…"}` basta. A API preenche o resto:
gera um `codigo_sku` derivado do nome, atribui a categoria `Outras`,
`status: ATIVO`, `formato: SIMPLES` e `versao: 0`. Retorna o produto
completo, já com `id` e `id_legado`.

### `produto get` ✅, `produto delete` ✅

`GET /v1/produtos/{id}` e `DELETE /v1/produtos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID do produto |

`produto get` devolve o cadastro completo, com os blocos aninhados
`categoria`, `estoque`, `fiscal` (`ncm`, `cest`, `unidade_medida`),
`ecommerce`, `variacao[]` e `detalhe_kit[]`.

`produto delete` responde **204 No Content** (renderizado como `[]`) e a
exclusão é permanente: `produto get` passa a devolver 404.

### `produto update` ✅

`PATCH /v1/produtos/{id}` — atualiza apenas os campos enviados.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | ID do produto |
| `--json` | **sim** | Objeto JSON da atualização |

Responde **204 No Content** (renderizado como `[]`) — confirme o resultado
com `produto get`. Cada atualização bem-sucedida **incrementa `versao`**,
mas, diferente de `baixa update`, a API não exige que você envie a versão
atual: não há controle de concorrência otimista aqui.

### Catálogos de produtos ✅

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

### `produto ecommerce-categorias` ⚠️

`GET /v1/produtos/ecommerce-categorias`

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

---

## Serviços

Os payloads seguem o schema da API e são enviados sem transformação.

> **Serviços têm duas identidades, e os comandos não usam a mesma.**
> `id` é o uuid — é o que `servico get` e `servico update` consomem.
> `id_servico` é o inteiro legado — é o que **`servico delete` exige**.
> Trocar um pelo outro no delete devolve 400 reclamando de `int64`.

### `servico list` ✅

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

### `servico create` ✅

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

### `servico get` ✅ e `servico update` ✅

`GET /v1/servicos/{id}` e `PATCH /v1/servicos/{id}` — ambos pelo **uuid**.

`servico get` recebe `<id>`. `servico update` recebe `<id>` e `--json` com
os campos a atualizar; responde **204 No Content** (renderizado como `[]`),
então confirme o resultado com `servico get`.

### `servico delete` ✅

`DELETE /v1/servicos` — exclui serviços em lote. Resposta **204 No Content**
(renderizada como `[]`).

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | `{"ids":[…]}` com os **`id_servico` inteiros**, não os uuids |

```json
{"ids":[495926356]}
```

> **É exclusão lógica, e ela não aparece em `servico get`.** Depois do
> delete o serviço some das listagens — `servico list` deixa de trazê-lo e a
> contagem cai —, mas `servico get` pelo uuid **continua respondendo 200**
> com o registro intacto e `status` ainda `ATIVO`. Não dá para descobrir por
> `servico get` que um serviço foi excluído; use `servico list`. É o oposto
> de `produto delete`, que passa a devolver 404.

---

## Notas fiscais

A API só suporta **consulta** (NFe de produto emitida e NFS-e de serviço) e vínculo a MDF-e — não há emissão pelo CLI.

### `nota-fiscal list` ⚠️

`GET /v1/notas-fiscais`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicial` | não¹ | 1º dia do mês corrente | Início do intervalo (`YYYY-MM-DD`) |
| `--data-final` | não¹ | último dia do mês corrente | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (`10`, `20`, `50` ou `100`) |
| `--documento-tomador` | não | — | Filtra pelo documento (CPF/CNPJ) do tomador |
| `--numero-nota` | não | — | Filtra pelo número da nota fiscal |
| `--id-venda` | não | — | Filtra pelo ID da venda |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna somente NFe com status `EMITIDA` e `CORRIGIDA_SUCESSO` (outros status "em construção" na própria API).

### `nota-fiscal get` ⚠️

`GET /v1/notas-fiscais/{chave}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<chave>` | **sim** | Argumento posicional. Chave de acesso da nota fiscal |

A resposta da API é binária (o XML da NF-e ou, quando há carta de correção, um ZIP com o XML da NF-e e o(s) XML(s) da carta de correção), não JSON. Para manter o contrato de stdout do CLI, o comando devolve `{"content_base64", "content_type"}` — decodifique `content_base64` para obter os bytes originais.

### `nota-fiscal vincular-mdfe` ⚠️

`POST /v1/notas-fiscais/vinculo-mdfe` — **escrita síncrona**, resposta `204 No Content` (renderizada como `[]`).

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON com `chaves_acesso` (array), `identificador` e, opcionalmente, `status` (`AUTORIZADO`, `ENCERRADO` ou `CANCELADO`) |

### `nota-fiscal-servico list` ⚠️

`GET /v1/notas-fiscais-servico`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-competencia-de` | não¹ | 15 dias atrás | Emissão inicial (`YYYY-MM-DD`) |
| `--data-competencia-ate` | não¹ | hoje | Emissão final (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (`10`, `20`, `50` ou `100`) |
| `--ids` | não | — | UUID da nota fiscal de serviço; repetível |
| `--id-cliente` | não | — | UUID de cliente; repetível |
| `--numero-venda` | não | — | Filtra pelo número da venda |
| `--numero-nfse-inicial` / `--numero-nfse-final` | não | — | Intervalo de número da NFS-e |
| `--numero-rps-inicial` / `--numero-rps-final` | não | — | Intervalo de número do RPS |
| `--status` | não | — | Status (`PENDENTE`, `EMITIDA`, `CANCELADA`, etc.); repetível |
| `--tipo-negociacao` | não | — | `VENDA` ou `CONTRATO` |

¹ A API exige o intervalo e o limita a **15 dias**; o CLI supre com os últimos 15 dias (terminando hoje) e avisa em stderr — diferente do mês corrente usado pelos demais comandos de listagem.

Diferente de `nota-fiscal list`, retorna NFS-e em qualquer status.

---

## Vendas

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

### `venda list` ⚠️

`GET /v1/venda/busca`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| filtros | não | — | `--termo-busca`, `--data-inicio`, `--data-fim`, `--data-criacao-de`, `--data-criacao-ate`, `--campo-ordenado-ascendente`, `--campo-ordenado-descendente` (`NUMERO`, `CLIENTE` ou `DATA`), `--totais` (`WAITING_APPROVED`, `APPROVED`, `CANCELED` ou `ALL`) |

Diferente de `contrato list`, o intervalo de datas é opcional — a API não o exige. Retorna `{totais, quantidades, total_itens, itens[]}`.

### `venda create` ⚠️

`POST /v1/venda` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON da venda (`id_cliente`, `numero`, `situacao`, `data_venda` e `itens` são obrigatórios) |

### `venda get` ⚠️

`GET /v1/venda/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid ou id legado da venda |

### `venda update` ⚠️

`PUT /v1/venda/{id}` — **escrita síncrona**; a API não expõe `PATCH` para vendas, então o payload precisa trazer o objeto completo, incluindo `versao`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da venda |
| `--json` | **sim** | Payload JSON completo da venda |

### `venda imprimir` ⚠️

`GET /v1/venda/{id}/imprimir`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid ou id legado da venda |

A resposta da API é um PDF binário, não JSON. Para manter o contrato de stdout do CLI, o comando devolve `{"content_base64", "content_type"}` — decodifique `content_base64` para obter os bytes originais.

### `venda itens` ⚠️

`GET /v1/venda/{id_venda}/itens`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `<id-venda>` | **sim** | — | Argumento posicional. Uuid da venda |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página |

Retorna `{itens[], itens_totais, totais}`.

### `venda vendedores` ⚠️

`GET /v1/venda/vendedores`

Sem parâmetros e sem paginação: devolve o array completo de vendedores cadastrados (`id`, `nome`, `id_legado`).

### `venda proximo-numero` ⚠️

`GET /v1/venda/proximo-numero`

Sem parâmetros. Retorna o próximo número de venda disponível como um inteiro solto (ou `null`), não um objeto — mesmo formato de `contrato proximo-numero`.

### `venda excluir-lote` ⚠️

`POST /v1/venda/exclusao-lote` — exclui vendas em lote.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON com `{"ids": [...]}` — de 1 a 10 uuids por chamada |

Retorna `{atualizados, ignorados}`.

---

## Orçamentos

O payload de criação segue o schema da API e é enviado sem transformação. Use `--json` com um objeto JSON.

### `orcamento list` ⚠️

`GET /v1/orcamentos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| filtros | não | — | `--termo-busca`, `--data-inicio`, `--data-fim`, `--data-criacao-de`, `--data-criacao-ate`, `--data-alteracao-de`, `--data-alteracao-ate`, `--campo-ordenado-ascendente`, `--campo-ordenado-descendente` (`DATA`, `NUMERO` ou `CLIENTE`) |

O intervalo de datas é opcional — a API não o exige. A API também aceita filtros por array (`ids_vendedores`, `ids_clientes`, `ids_natureza_operacao`, `ids_categorias`, `ids_produtos`, `situacoes`, `origens`, `numeros`, `ids_legado_donos`, `ids_legado_clientes`, `ids_legado_produtos`); eles não estão expostos como opções porque o comando genérico de listagem só suporta filtros escalares hoje. Retorna `{itens[], total_itens}`.

### `orcamento create` ⚠️

`POST /v1/orcamentos` — **escrita síncrona**, sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do orçamento (`data_orcamento`, `data_validade`, `id_cliente` e `itens` são obrigatórios) |

Retorna `{id}` do orçamento criado.

### `orcamento get` ⚠️

`GET /v1/orcamentos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do orçamento |

### `orcamento excluir-lote` ⚠️

`DELETE /v1/orcamentos` — exclui orçamentos em lote.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON com `{"ids": [...]}` — de 1 a 10 uuids por chamada |

Resposta `204 No Content` — sem corpo.

## Captura

Fluxo da IA Captura: `captura enviar` sobe um arquivo e devolve o `id` do
documento; `captura status` consulta esse `id` e, quando o processamento
termina, devolve a `id_captura`; `captura get` traz a prévia extraída para
essa `id_captura`; `captura aceitar`/`captura recusar` decidem o que fazer
com a prévia.

### `captura enviar` ⚠️

`POST /v1/captura/documentos` — multipart/form-data.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<arquivo>` | **sim** | Argumento posicional. Caminho de um arquivo local (PDF, JPEG, PNG ou BMP; máximo de 10 MB) |
| `--descricao` | não | Descrição do documento (máximo de 255 caracteres) |

O CLI valida que o arquivo existe e é legível antes de enviar. Retorna `{id, nome}`, onde `id` identifica o documento para `captura status`.

### `captura status` ⚠️

`GET /v1/captura/documentos/status`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--ids` | **sim** | — | IDs de documentos separados por vírgula (até 20) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (máximo 20) |

Retorna `{itens[], paginacao}`; cada item traz `status_documento` e a lista de `capturas` geradas (pode estar vazia). O processamento é assíncrono — repita a consulta até o status chegar a um estado final.

### `captura get` ⚠️

`GET /v1/captura/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura`, obtido em `captura status` |

Retorna `{id, id_documento, status, previa_evento_financeiro, sugestao_evento_financeiro}`. A prévia e a sugestão só vêm preenchidas quando `status` é `PENDENTE`.

### `captura aceitar` ⚠️

`POST /v1/captura/{id}` — sem corpo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura` cuja prévia será aceita |

Cria o evento financeiro a partir da prévia e retorna `{id, status, evento_financeiro}`.

### `captura recusar` ⚠️

`DELETE /v1/captura/{id}` — sem corpo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura` a ser recusada |

Resposta `204 No Content` — sem corpo. Uma captura já aceita, ou ainda em processamento, não pode ser recusada.

---

## Fora do escopo do CLI

Todos os 83 endpoints publicados no portal já têm comando — veja a
referência rápida no topo deste arquivo e `API_COVERAGE.md` para a lista
completa por área (Contratos, Notas Fiscais, Vendas, Orçamentos, Captura,
Cobranças e Baixas foram implementados além do escopo original declarado
em `ESPECIFICACAO.md`; as duas últimas descobertas só em 2026-08-19,
porque vivem em specs OpenAPI próprios sem link na página inicial do
portal).

Recursos que **não existem** na API v1 — não procure o comando, não há endpoint:

**lançamentos**, busca por id de conta a pagar/receber, update/delete desses eventos, e criação de transferência.

> Comandos para esses recursos já existiram, apontando para paths inventados, e retornavam 404. Foram removidos em vez de continuarem anunciando o que não funciona. Um comando `cobrança` também já existiu nessa situação e foi removido — não confundir com os comandos reais de hoje, `cobranca create`/`get`/`delete`, que usam o path correto (`.../contas-a-receber/gerar-cobranca` e `.../contas-a-receber/cobranca/{id}`), descoberto na sessão de 2026-08-19.

---

## Notas para quem for estender

### A API falha em silêncio mais do que falha em voz alta

**A armadilha mais cara deste projeto:** as listagens respondem `200` e
**descartam sem avisar** qualquer parâmetro de query que não reconheçam. Um
filtro com o nome errado não devolve `400` — devolve **a coleção inteira**,
que parece um resultado legítimo. Foi assim que `produto list --codigo`
(mandava `codigo`, a API quer `sku`) e três dos quatro filtros de
`servico list` passaram despercebidos por várias versões.

Comprovado com um parâmetro propositalmente inexistente (`zzz_bogus=abc`),
que se comporta exatamente como os nomes errados que o CLI mandava.

### Receita para verificar uma listagem

Nunca conclua que um filtro funciona porque a chamada respondeu 200.

1. **Baseline.** Pegue o total sem nenhum filtro.
2. **Controle.** Mande `zzz_bogus=abc`. Se o total não mudar, o endpoint
   descarta em silêncio e **todo** filtro precisa ser provado um a um.
3. **Valor discriminante.** Teste cada filtro com um valor que case com
   **um único registro** e confirme que o total cai. Um valor que casa com
   tudo não distingue "funciona" de "ignorado" — `status=ATIVO` devolvendo
   o catálogo inteiro é ambíguo quando todos os registros estão ativos;
   `status=INATIVO` devolvendo `0` é prova.
4. **Se falhar, varra nomes** antes de concluir que o filtro não existe:
   singular/plural, prefixos (`id_`, `filtro_`), sufixos (`_textual`,
   `_servico`), inglês, `[]`, repetido e separado por vírgula.

O mesmo ceticismo vale para escrita: **a API valida um campo por vez**, então
cada `400` revela só o *próximo* campo faltante. Descobrir o payload de um
`PUT` é iterativo — veja a tabela de `pessoa update`, montada assim.

### Ver o status HTTP real

O stdout não expõe o código de status, e `204` vs `200` importa (um corpo
vazio sai como `[]`, não `{}`). Use:

```sh
./bin/ca <comando> --debug …
tail -5 ~/.cache/conta-azul-cli/log.jsonl
```

### Armadilhas de nomenclatura já confirmadas

1. **O segmento `/financeiro/` só existe em parte dos recursos.** Categorias, centros de custo e contas financeiras ficam na raiz da `v1`.
2. **A nomenclatura alterna plural e singular:** `categorias`, mas `centro-de-custo` e `conta-financeira`.
3. **Leitura e escrita usam nomes diferentes para o mesmo dado.** `pessoa` lê `documento` e escreve `cpf`; o SKU de um produto é `codigo` na listagem, `codigo_sku` no detalhe e `sku` na query.
4. **O mesmo registro tem dois ids, e comandos do mesmo grupo usam ids diferentes.** `servico get`/`update` querem o uuid; `servico delete` quer o `id_servico` inteiro.
5. **Enums vão acentuados e capitalizados como na interface** (`Física`, `Cliente`), não em `SCREAMING_SNAKE_CASE`.
6. **Exclusão não quer dizer a mesma coisa em todo grupo.** `produto delete` faz o `get` passar a 404; `servico delete` é lógico e o `get` continua respondendo 200 com `status` `ATIVO`.

Nunca deduza da documentação **nem o path, nem o nome de um filtro, nem o
nome de um campo do payload, nem o tipo de um id, nem o formato da
resposta**, sem exercitar contra a API real.

### Onde travar o que você descobrir

| O que | Onde |
|---|---|
| Path e método de um endpoint | `tests/Unit/Api/*ClientTest.php` |
| Mapeamento opção da CLI → parâmetro de query | `tests/Integration/Command/Module/*CommandModuleTest.php` |
| Filtro removido por não existir | idem, com asserção negativa (`assertFalse(hasOption(...))`) |

O segundo caso é o que pegou os bugs de 2026-08-19 e **não existia antes
deles** — o teste de cliente passava feliz, porque o cliente repassa
qualquer filtro que recebe. Se você mexer em `$filters` num
`*CommandModule`, escreva o teste de mapeamento junto.

### Estado da verificação

| Grupo | Situação |
|---|---|
| Leituras financeiras | ✅ verificadas em 2026-08-15 |
| `pessoa` | ✅ 10/10 (2026-08-19) — nenhum bug de código |
| `produto` | ✅ 10/11 (2026-08-19) — 1 filtro errado, 2 inexistentes; falta `ecommerce-categorias` |
| `servico` | ✅ 5/5 (2026-08-19) — 1 filtro errado, 3 inexistentes |
| resto | ⚠️ nunca exercitado — trate os filtros como suspeitos |

Os próximos grupos com muitos filtros (`venda list`, `orcamento list`,
`contrato list`) são os de maior risco, pela mesma razão que produtos e
serviços foram.

Lista autoritativa de operações: https://developers.contaazul.com/docs/financial-apis-openapi/v1 — o portal bloqueia `curl` e fetch automatizado (403), então abra no navegador. Só três specs aparecem linkadas em `/aboutapis` (financial, sales, contracts); produtos, serviços e pessoas **não têm spec pública encontrável**, e o caminho `/_bundle/open-api-docs/{slug}.json`, que já funcionou, hoje devolve 404 — na prática, esses grupos só se descobrem exercitando.

Ver também: [`README.md`](README.md) para instalação, configuração e OAuth; [`docs/financial-apis-openapi.yaml`](docs/financial-apis-openapi.yaml) para o inventário de endpoints usado na detecção de drift.
