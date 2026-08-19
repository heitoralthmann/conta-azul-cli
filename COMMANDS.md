# Referência de comandos

Documentação canônica da superfície do `ca`: todos os comandos, agrupados pelo endpoint que consomem, com seus parâmetros.

**Este arquivo é a fonte de verdade sobre o que o CLI faz.** Ao alterar a integração — endpoint novo, filtro novo, comando removido — atualize-o no mesmo commit. Se ele divergir do código, ele deixa de servir ao propósito.

Convenção de leitura: `obrig.` marca o que falha sem valor; `padrão` é o que o CLI assume quando você omite.

Cada endpoint traz uma marca de confiança:

- **✅ verificado** — exercitado contra a API real e respondeu como documentado. As leituras financeiras foram verificadas em 2026-08-15; os grupos `pessoa`, `produto`, `servico`, `venda`, `orcamento` e `contrato` tiveram o CRUD completo (criar, ler, atualizar, excluir) exercitado em produção em 2026-08-19, e `notas fiscais` — que só tem leitura e uma escrita — foi exercitado por inteiro na mesma data
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
> chamada real.
>
> Sete grupos verificados depois, **todos tinham pelo menos um defeito**, mas
> o defeito mudou de lugar: os quatro últimos (`venda`, `orcamento`,
> `contrato`, `notas fiscais`) não tinham filtro errado nenhum. Erraram em
> campo obrigatório não documentado, par de campos trocado entre escrita e
> leitura, contador que não conta, e — em `nota-fiscal list` — um **default
> que o próprio endpoint recusa**, fazendo o comando sem argumentos falhar
> sempre.

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
| `contrato list` | `GET /v1/contratos` | ✅ |
| `contrato create` | `POST /v1/contratos` | ✅ |
| `contrato proximo-numero` | `GET /v1/contratos/proximo-numero` | ✅ |
| `contrato get` | `GET /v1/contratos/{id}` | ✅ |
| `contrato delete` | `DELETE /v1/contratos/{id}` | ✅ |
| `contrato encerrar` | `POST /v1/contratos/{id}/encerrar` | ✅ |
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
| `nota-fiscal list` | `GET /v1/notas-fiscais` | ✅ |
| `nota-fiscal get` | `GET /v1/notas-fiscais/{chave}` | ✅ |
| `nota-fiscal vincular-mdfe` | `POST /v1/notas-fiscais/vinculo-mdfe` | ✅ |
| `nota-fiscal-servico list` | `GET /v1/notas-fiscais-servico` | ✅ |
| `venda list` | `GET /v1/venda/busca` | ✅ |
| `venda create` | `POST /v1/venda` | ✅ |
| `venda get` | `GET /v1/venda/{id}` | ✅ |
| `venda update` | `PUT /v1/venda/{id}` | ✅ |
| `venda imprimir` | `GET /v1/venda/{id}/imprimir` | ✅ |
| `venda itens` | `GET /v1/venda/{id_venda}/itens` | ✅ |
| `venda vendedores` | `GET /v1/venda/vendedores` | ✅ |
| `venda proximo-numero` | `GET /v1/venda/proximo-numero` | ✅ |
| `venda excluir-lote` | `POST /v1/venda/exclusao-lote` | ✅ |
| `orcamento list` | `GET /v1/orcamentos` | ✅ |
| `orcamento create` | `POST /v1/orcamentos` | ✅ |
| `orcamento get` | `GET /v1/orcamentos/{id}` | ✅ |
| `orcamento excluir-lote` | `DELETE /v1/orcamentos` | ✅ |
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

**Três listagens param em `100`**, e a validação local sabe disso desde
2026-08-19:

| Listagem | Máximo |
|---|---|
| `servico list`, `nota-fiscal list`, `nota-fiscal-servico list` | `100` |
| todas as outras | `1000` |

Nessas três, `--tamanho-pagina 200` falha na hora com
`Valores aceitos: 10, 20, 50, 100`, sem gastar uma ida à API. Antes a
validação usava a lista larga para todo mundo, então o `200` passava aqui e
voltava `400` de lá — justamente o que validar localmente deveria evitar.

Os limites foram **medidos** endpoint a endpoint contra a produção, não
deduzidos: aceitam `1000` as listagens de produtos (e seus catálogos),
pessoas, vendas, itens de venda, orçamentos, contratos, transferências,
contas a pagar/receber, categorias, centros de custo e contas financeiras.
Só `captura status` ficou sem medir, por exigir um id de documento real.

E o nome do campo de contagem **não é o mesmo em todo lugar**:

| Formato | Onde |
|---|---|
| `{itens_totais, itens[]}` | comandos financeiros |
| `{totalItems, items[]}` | `pessoa list`, `produto list` |
| `{total_items, items[]}` | catálogos de `produto` (categorias, cest, ncm, …) |
| `{itens[], paginacao{total_itens}}` | `servico list`, `nota-fiscal list`, `nota-fiscal-servico list` |
| `{totais, quantidades, total_itens, itens[]}` | `venda list` |
| `{itens[], itens_totais, totais}` | `venda itens` |
| `{itens[], total_itens}` | `orcamento list` |
| `{itens_totais, itens[]}` | `contrato list` |

`venda` sozinha traz duas dessas variações — a listagem e os itens de uma
venda usam formatos diferentes. E `contrato list` mostra por que nem o nome
da chave se deduz: estava documentado como `{itens_totais, items[]}` e a API
devolve `itens`, não `items`.

E o campo de contagem **pode simplesmente estar errado**: o `total_itens` de
`orcamento list` ignora os orçamentos em `ORCAMENTO_RECUSADO` que a mesma
resposta devolve dentro de `itens`. Ao verificar uma listagem, conte
`len(itens)` em vez de confiar no total — foi só assim que essa divergência
apareceu.

## Datas

Dois formatos, e eles não são intercambiáveis:

| Contexto | Formato | Exemplo |
|---|---|---|
| Vencimento e baixa | `YYYY-MM-DD` | `2026-08-01` |
| `financeiro alteracoes` | ISO 8601 **sem timezone** | `2026-08-01T00:00:00` |

> Em `alteracoes`, sufixo `Z` ou offset (`-03:00`) faz a API responder **400**.

Onde há intervalo de datas, a API o **exige**. Omitir as opções não causa erro: o CLI assume o **mês corrente** e avisa em stderr qual recorte aplicou. Informar ambas as opções silencia o aviso. Exceção: as **duas listagens de notas fiscais** (`nota-fiscal list` e `nota-fiscal-servico list`), cujo intervalo é limitado a **15 dias** pela API — o default nelas são os **últimos 15 dias**, não o mês corrente.

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

**Verificado contra produção em 2026-08-19** — os seis comandos, com dois
contratos de teste criados, um encerrado e ambos excluídos. Os seis
parâmetros de `contrato list` passaram na receita completa (baseline,
`zzz_bogus=abc`, valor discriminante). Três avisos antes das tabelas:

> **1. Criar um contrato cria vendas na hora.** Um contrato mensal com
> `tipo_expiracao: NUNCA` e `data_fim` a um ano gerou **25 vendas agendadas**
> de uma vez, com `data_ultima_emissao` em 2028 — o `NUNCA` ignora o
> `data_fim` e agenda dois anos à frente. O mesmo contrato com
> `tipo_expiracao: DATA` e janela de um mês gerou 2. Escolha a janela antes
> de testar em produção.
>
> **2. `contrato delete` não é exclusão permanente**, ao contrário do que
> este documento afirmava. O `GET` continua respondendo `200`, com
> `status: DELETADO`; o que some é a listagem. As vendas associadas **são**
> canceladas de verdade (a contagem de vendas voltou ao valor de antes).
>
> **3. Um uuid válido mas inexistente devolve `500`, não `404`** — em
> `contrato get`, `contrato delete` e `contrato encerrar`. Nas duas escritas
> o CLI classifica isso como `ambiguous` e sugere reconciliar, quando na
> prática não havia nada para aplicar. Um id malformado, esse sim, devolve
> `400`.

### `contrato list` ✅

`GET /v1/contratos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | 1º dia do mês corrente | Início do intervalo (`YYYY-MM-DD`) |
| `--data-fim` | não¹ | último dia do mês corrente | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (aceita até `1000`) |
| `--busca-textual` | não | — | Busca textual: casa **nome do cliente e número do contrato** |
| `--cliente-id` | não | — | Filtra pelo ID do cliente |
| `--campo-ordenado-ascendente` | não | — | `DATA_INICIO` ou `DATA_FIM`; se informado, ignora `--campo-ordenado-descendente` |
| `--campo-ordenado-descendente` | não | — | `DATA_INICIO` ou `DATA_FIM` |

¹ A API exige o intervalo — **os dois lados**. Sem nenhum, responde `400`
("Data de início da recorrência não pode ser nula"); só com um lado, cobra o
outro. O CLI supre com o mês corrente e avisa em stderr, comportamento
confirmado exercitando.

Retorna **`{itens_totais, itens[]}`** — a chave é `itens`, não `items` como
este documento dizia. Cada item traz `{id, cliente, status,
proximo_vencimento, total_proximo_vencimento, data_inicio, numero,
conta_financeira, termos, tipo_pagamento, total}`; é um recorte diferente do
que `contrato get` devolve.

`--busca-textual` não busca um "nome do contrato" — esse campo não existe no
recurso. Casa o nome do cliente e o número: `busca_textual=2` devolveu só o
contrato de número 2. Ordenação inválida devolve `400` listando
`[DATA_INICIO, DATA_FIM]`, que é como se prova que o parâmetro é lido e não
descartado; mandando as duas ordenações juntas, a ascendente vence.

### `contrato create` ✅

`POST /v1/contratos` — **escrita síncrona**, diferente das escritas financeiras: não devolve protocolo, o `id` do contrato já vem na resposta.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do contrato, repassado à API **verbatim** |

O CLI não valida o conteúdo de `--json`. A lista de obrigatórios que este
documento trazia (`id_cliente`, `termos`, `condicao_pagamento`, `itens`) é
verdadeira mas inútil: a API cobra **onze campos**, descobertos um `400` de
cada vez.

| Campo | Onde | Formato |
|---|---|---|
| `id_cliente` | raiz | uuid da pessoa |
| `tipo_frequencia` | `termos` | `MENSAL` ou `ANUAL` |
| `tipo_expiracao` | `termos` | `DATA` ou `NUNCA` |
| `data_inicio` | `termos` | `YYYY-MM-DD` |
| `data_fim` | `termos` | `YYYY-MM-DD` — **exigido mesmo com `tipo_expiracao: NUNCA`** |
| `intervalo_frequencia` | `termos` | inteiro |
| `dia_emissao_venda` | `termos` | inteiro (dia do mês) |
| `numero` | **`termos`** | inteiro — veja abaixo |
| `tipo_pagamento` | `condicao_pagamento` | enum longo (`BOLETO_BANCARIO`, `PIX_PAGAMENTO_INSTANTANEO`, `DINHEIRO`, …) |
| `dia_vencimento` | `condicao_pagamento` | inteiro |
| `primeira_data_vencimento` | `condicao_pagamento` | `YYYY-MM-DD` |
| `itens` | **raiz** | array de `{id, quantidade, valor}` |

Dois desses lugares custam tempo se você supuser errado:

- **`numero` fica dentro de `termos`.** Na raiz — e como `numero_contrato`,
  `num_contrato`, `numero_do_contrato`, `contrato_numero` ou `number` — a API
  repete "O número do contrato é obrigatório" sem dizer onde ela quer.
- **`itens` fica na raiz**, não em `termos`. Dentro de `termos` a resposta é
  "Lista de itens da recorrência não pode ser vazia".

Retorna **`{id, id_legado}`** — não há `id_venda`, ao contrário do que este
documento afirmava.

**`observacoes` volta em outro lugar.** O que você manda na raiz como
`observacoes` aparece no `GET` em `condicao_pagamento.observacoes_pagamento`;
a `observacoes` da raiz fica sempre vazia na leitura, e uma
`observacoes_pagamento` mandada dentro de `condicao_pagamento` é descartada.
Mesma classe de troca de `orcamento create`.

Payload mínimo que passou:

```json
{
  "id_cliente": "a1523431-f1be-44c4-8413-fdb5e50643e3",
  "termos": {
    "tipo_frequencia": "MENSAL",
    "tipo_expiracao": "DATA",
    "data_inicio": "2026-09-01",
    "data_fim": "2026-10-01",
    "intervalo_frequencia": 1,
    "dia_emissao_venda": 1,
    "numero": 1
  },
  "condicao_pagamento": {
    "tipo_pagamento": "BOLETO_BANCARIO",
    "dia_vencimento": 10,
    "primeira_data_vencimento": "2026-09-10"
  },
  "itens": [{ "id": "1a1b7957-12c7-4064-9809-5af7bbb40f57", "quantidade": 1, "valor": 10 }]
}
```

A `descricao` de um item **propaga para as vendas geradas**, o que a torna o
lugar prático para marcar um contrato de teste.

### `contrato proximo-numero` ✅

`GET /v1/contratos/proximo-numero`

Sem parâmetros. Retorna o próximo número de contrato disponível como um inteiro solto, não um objeto — diferente de todos os outros comandos de leitura.

O contador acompanha as exclusões: numa conta sem contratos devolveu `1`,
subiu para `2` depois do contrato de número 1 e voltou a `1` quando os
contratos de teste foram excluídos. Ao contrário de `venda`, esse número
**não** é atribuído sozinho — é você quem manda `termos.numero` no `create`.

### `contrato get` ✅

`GET /v1/contratos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

Devolve bem mais do que a listagem: `{id, id_ultima_venda_confirmada,
id_proxima_venda_agendada, cliente, vendedor, termos, data_proximo_vencimento,
data_proxima_emissao, data_ultima_emissao, status, configuracao_recorrencia,
condicao_pagamento, local_prestacao_servico, composicao_valor, observacoes}`.

`status` é `ATIVO`, `INATIVO` (depois de `encerrar`) ou `DELETADO` (depois de
`delete`). Uuid inexistente devolve `500`; id malformado, `400`.

### `contrato delete` ✅

`DELETE /v1/contratos/{id}` — exclusão **lógica**, apesar do nome.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

Resposta `204 No Content` — sem corpo, que o CLI imprime como `[]`.

O contrato sai da listagem mas continua legível por `contrato get`, com
`status: DELETADO` e `id_proxima_venda_agendada` zerado. **As vendas
associadas são canceladas de verdade**: um contrato que havia gerado 25
vendas agendadas devolveu a contagem global de vendas ao valor anterior
depois do `delete`. Funciona também em contrato já encerrado.

Contratos em reajuste de valor não podem ser removidos.

### `contrato encerrar` ✅

`POST /v1/contratos/{id}/encerrar` — sem corpo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do contrato |

Resposta `204 No Content` — sem corpo, que o CLI imprime como `[]`.

Desativa o contrato: `status` passa de `ATIVO` para `INATIVO` e
`id_proxima_venda_agendada` é zerado, mas o contrato **permanece na
listagem** — é o que separa `encerrar` de `delete`. A chamada é idempotente:
repetida num contrato já `INATIVO`, responde `204` de novo.

Contratos em reajuste de valor não podem ser encerrados.

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

**Verificado contra produção em 2026-08-19** — os quatro comandos, incluindo o
ciclo completo de `vincular-mdfe` (`AUTORIZADO` → `ENCERRADO` → `CANCELADO`)
sobre notas de 2024 marcadas com `identificador` `TESTE HEITOR`.

> **As duas listagens limitam o intervalo a 15 dias**, não só a de serviço.
> `nota-fiscal list` respondia `400` em *toda* invocação sem datas, porque o
> default era o mês corrente. Medido: 15 dias de diferença passam, 16 respondem
> `{"error":"O período entre data_inicial e data_final não pode ser maior que
> 15 dias"}`. Corrigido — o default agora são os últimos 15 dias, como na NFS-e.

### `nota-fiscal list` ✅

`GET /v1/notas-fiscais`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicial` | não¹ | 15 dias atrás | Início do intervalo (`YYYY-MM-DD`) |
| `--data-final` | não¹ | hoje | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (`10`, `20`, `50` ou `100`) |
| `--documento-tomador` | não | — | Documento (CPF/CNPJ) do tomador, só dígitos |
| `--numero-nota` | não | — | Número da nota fiscal |
| `--id-venda` | não | — | **UUID** da venda |

¹ A API exige o intervalo e o limita a 15 dias; o CLI supre e avisa em stderr.

Os três filtros foram provados um a um com valor que casa com um único
registro — obrigatório, porque a listagem **responde 200 e descarta em
silêncio** o que não reconhece (`zzz_bogus=abc` devolve a coleção inteira).

> **`--id-venda` quer o UUID, não o `id_legado`.** É o raro caso em que a API
> valida: o inteiro devolve `400 {"error":"O valor informado deve estar no
> formato UUID válido"}`. Só `numero_nota` funciona como nome do filtro de
> número — `numero`, `numeroNota`, `numero_nf`, `nota`, `numero_documento` e
> `numero_nota_fiscal` são todos descartados em silêncio.

**Resposta:** `{itens[], paginacao{pagina_atual, total_paginas, tamanho_pagina, total_itens}}`.

> **`total_itens` superconta, e muito.** Ele conta as notas de *todos* os
> status, mas `itens` só traz `EMITIDA` e `CORRIGIDA_SUCESSO`. Numa janela real
> a resposta foi `itens: []` com `total_itens: 3`; noutra, 7 itens com
> `total_itens: 12`. Varrendo dois anos: 147 notas devolvidas, 146 `EMITIDA` e
> 1 `CORRIGIDA_SUCESSO`. **Conte `len(itens)`** — é o mesmo defeito de
> `orcamento list`, aqui numa escala que chega a 100% de divergência.

> Sem nenhum resultado, a API devolve `tamanho_pagina: 9223372036854775807`
> (o `PHP_INT_MAX`) em vez do tamanho pedido.

### `nota-fiscal get` ✅

`GET /v1/notas-fiscais/{chave}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<chave>` | **sim** | Argumento posicional. Chave de acesso (44 dígitos) |

A resposta da API é binária, não JSON. Para manter o contrato de stdout, o
comando devolve `{"content_base64", "content_type"}` — decodifique
`content_base64` para obter os bytes originais. **`content_type` é sempre
`application/octet-stream`**, inclusive no XML puro, então ele não serve para
distinguir os dois formatos; olhe os bytes:

| Status da nota | Conteúdo | Como reconhecer |
|---|---|---|
| `EMITIDA` | XML da NF-e | começa com `<?xml` |
| `CORRIGIDA_SUCESSO` | ZIP | começa com `PK\x03\x04` |

O ZIP verificado trazia `XML_174_1.xml` (a NF-e) e `EVENTO_174_1.xml` (a carta
de correção).

> Chave inexistente devolve **`404`** com `{"error":"Nenhuma nota fiscal
> encontrada com a chave informada"}` — e uma string que nem chave é (`NOTAVALIDA`)
> devolve o mesmo `404`, não um `400` de formato. Diferente de `contrato get`,
> que responde `500` para id desconhecido.

### `nota-fiscal vincular-mdfe` ✅

`POST /v1/notas-fiscais/vinculo-mdfe` — **escrita síncrona**, resposta `204 No Content` (renderizada como `[]`).

Registra na Conta Azul que um conjunto de notas fiscais pertence a um MDF-e
(Manifesto Eletrônico de Documentos Fiscais, modelo 58) emitido em outro
sistema. **Não emite MDF-e e não transmite nada à SEFAZ** — o payload não tem
veículo, motorista nem percurso, que a SEFAZ exigiria.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do vínculo |

Campos do payload — **os três são obrigatórios**:

| Campo | Tipo | Observação |
|---|---|---|
| `chaves_acesso` | array de string | Chaves de acesso das NF-e. Aceita mais de uma |
| `identificador` | string | **Texto livre** — não é validado como chave de MDF-e |
| `status` | string | `AUTORIZADO`, `ENCERRADO` ou `CANCELADO`, caixa-alta exata |

> **`status` é obrigatório**, ao contrário do que esta página afirmava até
> 2026-08-19. Sem ele a API responde `400` com a lista de valores aceitos, e
> ela valida esse campo **antes** dos demais — por isso ele é o primeiro erro
> que aparece, mesmo faltando os outros dois.

Ordem de validação observada, um campo por vez: `status` → campos obrigatórios
(`chaves_acesso`, `identificador`) → existência das chaves.

**Comportamento confirmado contra produção:**

| Cenário | Resultado |
|---|---|
| Uma chave válida, qualquer `status` | `204` |
| Duas chaves na mesma chamada | `204` — o array é mesmo plural |
| Repetir a mesma chave e `identificador` | `204`, sem erro de duplicata |
| Mesma chave com `identificador` diferente | `204`, também aceito |
| `AUTORIZADO` → `ENCERRADO` → `CANCELADO` | `204` em todas; nenhuma máquina de estados é imposta |
| `AUTORIZADO` depois de `CANCELADO` | `204` — cancelar não trava a chave |
| `status` em minúsculas | `400` |
| Chave inexistente | `404` |
| Chave válida **junto de** uma inexistente | `404` (ver abaixo) |
| `chaves_acesso: []` | **`500`**, não `400` |

> **O vínculo não é legível por lugar nenhum da API.** Não há `GET` do vínculo,
> a nota não muda no `list`, e o XML devolvido por `nota-fiscal get` continua
> **byte a byte idêntico** (conferido por SHA-256 antes e depois, em quatro
> notas). Isso é a boa notícia — a escrita não toca o documento fiscal — mas
> significa que **não dá para verificar nem desfazer um vínculo pelo CLI**.
> Como repetir a chamada nunca dá erro, também não existe sonda indireta.

> **Atomicidade indeterminada.** Uma chamada com uma chave válida e uma
> inexistente devolve `404`. Se a válida chegou a ser vinculada, não há como
> saber — pela ordem de validação é provável que o lote inteiro seja rejeitado
> antes de gravar, mas isso **não está provado**. Mande chaves conferidas.

> `chaves_acesso: []` devolve `500`, que o CLI classifica como erro de escrita
> "a operação pode ter sido aplicada" e manda reconciliar. É alarme falso — nada
> foi gravado. A classificação é compartilhada por todos os grupos e por isso
> não foi mexida aqui.

### `nota-fiscal-servico list` ✅

`GET /v1/notas-fiscais-servico`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-competencia-de` | não¹ | 15 dias atrás | Competência inicial (`YYYY-MM-DD`) |
| `--data-competencia-ate` | não¹ | hoje | Competência final (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (`10`, `20`, `50` ou `100`) |
| `--ids` | não | — | UUID da nota fiscal de serviço; repetível |
| `--id-cliente` | não | — | UUID de cliente; repetível |
| `--numero-venda` | não | — | Número da venda |
| `--numero-nfse-inicial` / `--numero-nfse-final` | não | — | Intervalo de número da NFS-e |
| `--numero-rps-inicial` / `--numero-rps-final` | não | — | Intervalo de número do RPS |
| `--status` | não | — | `EMITIDA`, `CANCELADA`, `PENDENTE`, …; repetível |
| `--tipo-negociacao` | não | — | `VENDA` ou `CONTRATO` |

¹ A API exige o intervalo e o limita a **15 dias**; o CLI supre e avisa em stderr.

**Os onze filtros foram provados individualmente** contra produção, cada um com
um valor que casa com um subconjunto conhecido — esta listagem também descarta
em silêncio o que não reconhece. Foi o único grupo `⚠️` da campanha cujo
conjunto de filtros veio inteiro correto da documentação.

Os repetíveis (`--ids`, `--id-cliente`, `--status`) vão como array
(`ids[0]=…&ids[1]=…`) e a API casa por união: duas notas pedidas, duas
devolvidas.

**Resposta:** `{itens[], paginacao{…}}`, com `total_itens` **fiel** ao tamanho
de `itens` — ao contrário de `nota-fiscal list`. Diferente da NFe, devolve
NFS-e em qualquer status (numa varredura de um ano: 59 `EMITIDA`, 10 `CANCELADA`).

Cada item traz `id`, `id_venda`, `numero_venda`, `numero_rps`, `numero_nfse`,
`status`, `valor_total_nfse`, `data_competencia`, `nome_cliente`,
`documento_cliente`, `codigo_cnae`, `cidade_emissao{nome, estado}`,
`escriturado_manualmente` e `informacao_transmissao{data_inicio_emissao}`.

> `numero_venda` volta como **string** (`"7206"`) apesar de o filtro aceitar
> inteiro. E `tipo_negociacao`, que dá para filtrar, **não aparece** em nenhum
> item da resposta.

---

## Vendas

Os payloads de criação e atualização seguem o schema da API e são enviados sem transformação. Use `--json` com um objeto JSON.

**Verificado contra produção em 2026-08-19** — os nove comandos, incluindo o
ciclo completo de escrita (criar, atualizar e excluir três vendas de teste).
`venda list` foi o primeiro grupo em que **todos** os filtros documentados
existiam de verdade: os oito passaram na receita de baseline + `zzz_bogus` +
valor discriminante. As armadilhas do grupo estão na escrita, não na leitura.

### `venda list` ✅

`GET /v1/venda/busca`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |
| filtros | não | — | `--termo-busca`, `--data-inicio`, `--data-fim`, `--data-criacao-de`, `--data-criacao-ate`, `--campo-ordenado-ascendente`, `--campo-ordenado-descendente` (`NUMERO`, `CLIENTE` ou `DATA`), `--totais` (`WAITING_APPROVED`, `APPROVED`, `CANCELED` ou `ALL`) |

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

### `venda create` ✅

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
  "id_cliente": "a1523431-f1be-44c4-8413-fdb5e50643e3",
  "numero": 7297,
  "situacao": "EM_ANDAMENTO",
  "data_venda": "2026-08-19",
  "observacoes": "TESTE HEITOR",
  "itens": [{ "id": "1a1b7957-12c7-4064-9809-5af7bbb40f57", "quantidade": 1, "valor": 10 }],
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

### `venda get` ✅

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

### `venda update` ✅

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

### `venda imprimir` ✅

`GET /v1/venda/{id}/imprimir`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid ou id legado da venda |

A resposta da API é um PDF binário, não JSON. Para manter o contrato de stdout do CLI, o comando devolve `{"content_base64", "content_type"}` — decodifique `content_base64` para obter os bytes originais. Confirmado: `content_type` é `application/pdf` e os bytes decodificados começam com `%PDF-1.5`.

### `venda itens` ✅

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

### `venda vendedores` ✅

`GET /v1/venda/vendedores`

Sem parâmetros e sem paginação: devolve o array completo de vendedores cadastrados. Cada item traz apenas `{id, nome}` — o `id_legado` que a documentação promete **não vem na resposta**.

### `venda proximo-numero` ✅

`GET /v1/venda/proximo-numero`

Sem parâmetros. Retorna o próximo número de venda disponível como um inteiro solto (ou `null`), não um objeto — mesmo formato de `contrato proximo-numero`. O contador **volta atrás** quando as vendas são excluídas: passou de 7297 para 7298 assim que a venda 7297 foi criada e voltou a 7297 depois que as três vendas de teste foram removidas.

### `venda excluir-lote` ✅

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

---

## Orçamentos

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

### `orcamento list` ✅

`GET /v1/orcamentos`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página (aceita até `1000`) |
| filtros | não | — | `--termo-busca`, `--data-inicio`, `--data-fim`, `--data-criacao-de`, `--data-criacao-ate`, `--data-alteracao-de`, `--data-alteracao-ate`, `--campo-ordenado-ascendente`, `--campo-ordenado-descendente` (`DATA`, `NUMERO` ou `CLIENTE`) |

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

### `orcamento create` ✅

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
  "id_cliente": "a1523431-f1be-44c4-8413-fdb5e50643e3",
  "data_orcamento": "2026-08-19",
  "data_validade": "2026-09-19",
  "descricao": "TESTE HEITOR",
  "itens": [{ "id": "1a1b7957-12c7-4064-9809-5af7bbb40f57", "quantidade": 1, "valor": 10 }]
}
```

### `orcamento get` ✅

`GET /v1/orcamentos/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid do orçamento |

Devolve o orçamento **solto**, sem envelope — ao contrário de `venda get` — e
com os `itens` inclusos, também ao contrário de `venda get`. Id inexistente
devolve `404` com `"Orçamento não encontrado com o ID informado"`.

Lembre da troca de `observacoes` ↔ `observacoes_pagamento` ao ler o que você
mesmo escreveu.

### `orcamento excluir-lote` ✅

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

---

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

1. **Baseline.** Pegue o total sem nenhum filtro — contando `len(itens)`,
   não o campo de total. Em `orcamento list` os dois divergem.
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
6. **Exclusão não quer dizer a mesma coisa em todo grupo.** `produto delete` faz o `get` passar a 404; `servico delete` é lógico e o `get` continua respondendo 200 com `status` `ATIVO`; `venda excluir-lote` também é lógico e vira `status` `CANCELADO` — mas deixa `situacao` como estava.
7. **A resposta da escrita não fala a mesma língua que a da leitura.** `venda create` devolve `situacao.nome` em inglês (`IN_PROCESS`) para a venda que `venda get` mostra como `EM_ANDAMENTO`.
8. **Zero pode ser lido como ausente.** `venda update` exige `versao` e recusa `0` com "campo obrigatório" — justamente o valor que uma venda recém-criada tem.
9. **Dois campos podem estar simplesmente trocados.** O que `orcamento create` recebe em `observacoes` volta em `observacoes_pagamento` no `orcamento get`, e vice-versa; em `contrato create` a `observacoes` da raiz reaparece em `condicao_pagamento.observacoes_pagamento`. Escreva um valor distinto em cada campo suspeito e leia de volta: é a única forma de enxergar isso.
10. **O total de uma listagem pode não bater com o que ela devolve.** `orcamento list` responde `total_itens: 157` junto de 158 itens, porque o contador ignora `ORCAMENTO_RECUSADO`. Em `nota-fiscal list` a divergência chega a 100%: o contador soma todos os status, mas só `EMITIDA` e `CORRIGIDA_SUCESSO` vêm em `itens` — há janelas que devolvem `itens: []` com `total_itens: 3`.
11. **Um id inexistente nem sempre é `404`.** Em `contrato get`, `delete` e `encerrar`, um uuid válido que não existe devolve `500` — e nas escritas o CLI traduz isso para `ambiguous`, sugerindo reconciliar algo que nunca aconteceu.
12. **O default de um intervalo de datas pode ser inválido para o próprio endpoint.** `nota-fiscal list` limita a janela a 15 dias, mas o CLI mandava o mês corrente — então o comando sem argumentos falhava com `400` em 100% das vezes, e ninguém notou porque quem chamava sempre passava datas. Ao adicionar um default, exercite-o **sem argumento nenhum**.
13. **Uma escrita pode não ter leitura correspondente.** `nota-fiscal vincular-mdfe` grava um vínculo que nenhum `GET` devolve, que não altera a nota e que repetir nunca acusa duplicata. Sem `GET`, sem efeito colateral observável e sem erro de duplicata, não há como provar a limpeza — só dá para provar o que **não** mudou (o SHA-256 do XML da nota, idêntico antes e depois). Quando um grupo tiver escrita sem leitura, decida antes até onde vale exercitar.
12. **Um campo obrigatório pode estar aninhado onde você não procuraria.** O número do contrato é `termos.numero`, e a mensagem de erro ("O número do contrato é obrigatório") não diz onde. Se um nome óbvio não resolve, tente dentro de cada sub-objeto do payload antes de concluir que o nome está errado.

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
| `venda` | ✅ 9/9 (2026-08-19) — nenhum bug de filtro; as armadilhas estavam na escrita |
| `orcamento` | ✅ 4/4 (2026-08-19) — nenhum bug de filtro; `total_itens` conta errado e dois campos trocam de nome entre escrita e leitura |
| `contrato` | ✅ 6/6 (2026-08-19) — nenhum bug de filtro; payload de criação bem maior que o documentado e `delete` é lógico, não permanente |
| resto | ⚠️ nunca exercitado — trate os filtros como suspeitos |

`venda list` quebrou a sequência: era o grupo de mais filtros e todos os oito
existiam. Não conclua daí que dá para confiar na documentação — a mesma
verificação achou um campo obrigatório que ela não lista
(`condicao_pagamento`), um `versao` que rejeita o próprio valor do registro,
e um `id_legado` prometido em `venda vendedores` que não vem na resposta. O
risco só mudou de lugar.

Os quatro grupos de mais filtros já foram exercitados, e os três últimos
(`venda`, `orcamento`, `contrato`) não tinham um único filtro errado. O que
sobrou de risco mudou de lugar: agora está nos **payloads de escrita** e nos
**formatos de resposta**, onde os três grupos erraram — campo obrigatório
não documentado, campo aninhado em lugar inesperado, chave de resposta com
outro nome, contador que não conta, e dois pares de campos que trocam de
lugar entre escrita e leitura.

Lista autoritativa de operações: https://developers.contaazul.com/docs/financial-apis-openapi/v1 — o portal bloqueia `curl` e fetch automatizado (403), então abra no navegador. Só três specs aparecem linkadas em `/aboutapis` (financial, sales, contracts); produtos, serviços e pessoas **não têm spec pública encontrável**, e o caminho `/_bundle/open-api-docs/{slug}.json`, que já funcionou, hoje devolve 404 — na prática, esses grupos só se descobrem exercitando.

Ver também: [`README.md`](README.md) para instalação, configuração e OAuth; [`docs/financial-apis-openapi.yaml`](docs/financial-apis-openapi.yaml) para o inventário de endpoints usado na detecção de drift.
