<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Captura

Fluxo da IA Captura: `captura enviar` sobe um arquivo e devolve o `id` do
documento; `captura status` consulta esse `id` e, quando o processamento
termina, devolve a `id_captura`; `captura get` traz a prévia extraída para
essa `id_captura`; `captura aceitar`/`captura recusar` decidem o que fazer
com a prévia.

Verificado contra a produção em 2026-08-19 com dois recibos em PDF gerados
para o teste. **É o único grupo que escreve em outro cadastro sem avisar** e
o único cujo `id` de recurso não é uuid v4 — veja as duas notas abaixo.

> **`captura enviar` cria um fornecedor no cadastro de pessoas.** Não é o
> `aceitar` que faz isso: assim que a IA termina de extrair, o
> `previa_evento_financeiro.fornecedor` já vem com um `id` que
> `pessoa get` resolve, com `criado_em` do dia e perfil `Fornecedor`. Um
> segundo documento do mesmo CNPJ reaproveita o registro em vez de duplicar.
> Ou seja: **subir um documento é uma escrita no cadastro de pessoas**, não
> uma leitura. Quem for exercitar isso em produção deve contar com esse
> registro a mais.

> **Os ids da Captura são uuid v7**, não v4 (`01a01a96-61f5-7007-…`, com `7`
> na posição da versão). Qualquer validação local que exija v4 recusaria um
> id legítimo. A API, por sua vez, valida o formato: um id que não é uuid
> devolve `400` ("O ID da captura informado é inválido") e um uuid válido
> porém inexistente devolve `404` ("Captura não encontrada com o ID
> informado").

> **Este grupo devolve erro em outro envelope.** Onde o resto da API usa
> `{"timestamp", "status", "error", "message", "path"}`, a Captura responde
> `{"error": "mensagem"}`. Serve para distinguir rota inexistente de id
> inexistente: `/v1/captura/documentos/{id}` (rota que não existe) devolve o
> `404 page not found` em **texto puro** do gateway, enquanto as rotas reais
> devolvem JSON.

## `captura enviar` ✅ { #captura-enviar }

`POST /v1/captura/documentos` — multipart/form-data. Responde **`201`**, não `200`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<arquivo>` | **sim** | Argumento posicional. Caminho de um arquivo local (PDF, JPEG, PNG ou BMP; máximo de 10 MB) |
| `--descricao` | não | Descrição do documento (máximo de 255 caracteres) |

`200`.


O CLI valida que o arquivo existe e é legível antes de enviar. Retorna
`{id, nome}`, onde `id` identifica o documento para `captura status`.

O **tipo do arquivo é validado pela API**, não pelo CLI: um `.txt` volta
`415` com "Formato não suportado. Aceitos: PDF, JPEG, PNG, BMP." — mensagem
clara o bastante para não valer duplicar a regra localmente.

> **`--descricao` é escrita sem leitura.** Nenhuma das três respostas de
> leitura (`status`, `get`, `aceitar`) devolve a descrição enviada, e a
> descrição do evento financeiro criado vem do **texto que a IA extraiu do
> documento**, não dela. Serve para o histórico na interface web; pelo CLI
> não há como conferir o que foi gravado. O limite de 255 caracteres ficou
> **sem exercitar**: comprová-lo exigiria mais um upload, e documento
> enviado não tem como ser apagado (veja `captura recusar`).

## `captura status` ✅ { #captura-status }

`GET /v1/captura/documentos/status`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--ids` | **sim** | — | IDs de documentos separados por vírgula (até 20) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `10` | Itens por página (**qualquer inteiro de 1 a 20**) |

Retorna `{itens[], paginacao}`; cada item traz `status_documento` e a lista
de `capturas` geradas (pode estar vazia). O processamento é assíncrono —
repita a consulta até o status chegar a um estado final. Aqui o
`total_itens` **confere** com `len(itens)`.

> **`--ids` com mais de um id nunca funcionou até 2026-08-19.** O OpenAPI
> publicado declara `style: form, explode: false` — os ids juntos num
> parâmetro só, separados por vírgula — e a API responde `400` ("O valor
> informado para o campo 'ids' é inválido") exatamente a essa forma. Ela quer
> o parâmetro **repetido**: `ids=a&ids=b`. Com **um** id as duas formas
> coincidem, e foi por isso que o defeito atravessou incólume todo teste que
> passava um id só. Varridas e descartadas: vírgula, espaço, `|`, `;`, JSON
> e `ids[]=` (todas `400`). Funciona também `ids[0]=a&ids[1]=b`, que é o que
> a opção `query` do Symfony geraria — mas o CLI manda a forma repetida, que
> é a canônica.

Limites medidos, todos com mensagem própria: mais de 20 ids devolve `400`
("O campo 'ids' não pode conter mais de 20 itens"), e o CLI passou a barrar
isso antes da ida à API.

> **Zero é buraco na validação da API, e o CLI é mais rígido de propósito.**
> `tamanho_pagina=0` e `pagina=0` respondem `200` e caem no default (a
> resposta volta com `tamanho_pagina: 10, pagina_atual: 1`), enquanto `-1`
> devolve `400` ("deve ser maior ou igual a 1") — ou seja, a API valida o
> negativo e deixa o zero passar como se fosse ausente. O CLI **recusa**
> `--tamanho-pagina 0` localmente: zero não é um tamanho de página, e aceitar
> silenciosamente um valor que não faz o que foi pedido é o mesmo defeito do
> descarte silencioso. `--pagina 0` segue passando, e a API normaliza para 1. `--ids` vazio ou ausente é recusado pela própria
API (`400`), sem descarte silencioso — ao contrário do que acontece com
parâmetro desconhecido, que aqui também some sem avisar (`zzz_bogus=abc`
devolve a resposta inalterada).

Estados possíveis, do OpenAPI e confirmados no fluxo real —
`status_documento`: `PENDENTE`, `PROCESSANDO`, `EXTRAINDO_DADOS`,
`APLICANDO_REGRAS`, `AGUARDANDO_VINCULO_LANCAMENTO`,
`CRIANDO_LANCAMENTOS_FINANCEIROS`, `PRONTO`, `IGNORADO`, `RESOLVIDO`,
`ERRO`, `EXCLUIDO`; `status_captura`: `PROCESSANDO`, `PENDENTE`, `ACEITA`,
`REJEITADA`, `FALHA`. Na verificação o documento percorreu
`EXTRAINDO_DADOS` → `AGUARDANDO_VINCULO_LANCAMENTO` → `PRONTO` em poucos
segundos, e foi para `RESOLVIDO` depois do aceite.

## `captura get` ✅ { #captura-get }

`GET /v1/captura/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura`, obtido em `captura status` |

Retorna `{id, id_documento, status, previa_evento_financeiro}`. A prévia só
vem enquanto `status` é `PENDENTE`: depois do aceite a resposta encolhe para
`{id, id_documento, status}`, e depois da recusa o `get` passa a responder
**`404`**.

> **`sugestao_evento_financeiro` não existe na resposta real.** O OpenAPI a
> declara e este arquivo a documentava; a API nunca a devolveu, nem com a
> captura em `PENDENTE`. Documentar campo de resposta a partir da spec é o
> mesmo erro que documentar filtro a partir dela.

A `previa_evento_financeiro` traz `tipo`, `valor`, `data_competencia`,
`descricao`, `observacao`, `referencia_externa`, `fornecedor{id, nome,
documento}`, `categoria{id, nome}` e `parcelas[]` com `data_vencimento`,
`metodo_pagamento` e `composicao_valor`. Tanto o `fornecedor.id` quanto o
`categoria.id` apontam para registros que existem de verdade no cadastro.

## `captura aceitar` ✅ { #captura-aceitar }

`POST /v1/captura/{id}` — sem corpo. Responde `200`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura` cuja prévia será aceita |

Cria o evento financeiro a partir da prévia e retorna
`{id, status, evento_financeiro{id, valor, tipo, data_competencia,
descricao}}`.

> **Não há volta.** O evento financeiro criado é uma conta a pagar/receber
> comum, e a API **não publica `DELETE` de evento financeiro** — só sai pela
> interface web. Some-se a isso que o documento também não tem `DELETE` e a
> captura aceita não pode mais ser recusada: `aceitar` é a transição mais
> cara do CLI inteiro. Decida antes de chamar.

> **É idempotente, e isso é a parte boa.** Chamar `aceitar` de novo na mesma
> captura devolve `200` com `{id, status: "ACEITA"}` e **sem**
> `evento_financeiro` — não cria um segundo lançamento. Conferido contando
> `conta-a-pagar list` antes e depois: 37 → 38, com duas chamadas de aceite.

O evento criado guarda o caminho de volta: em `parcela get`, o
`evento.referencia` vem `{id: <id_captura>, origem: "LANCAMENTO_FINANCEIRO"}`.
É o único vínculo legível entre o financeiro e a captura que o originou.

## `captura recusar` ✅ { #captura-recusar }

`DELETE /v1/captura/{id}` — sem corpo. Resposta **`204 No Content`**, renderizada como `[]`.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. `id_captura` a ser recusada |

renderizada como `[]`.


> **É o único caminho de volta do grupo, e funciona.** Recusar a captura tira
> o documento da listagem de status — comprovado: consultar os dois ids
> enviados passou a devolver só o outro. Como não existe
> `DELETE /v1/captura/documentos/{id}` (a rota devolve o `404 page not found`
> do gateway), recusar é a **única** forma de fazer um documento enviado
> desaparecer. Se você for exercitar este grupo, planeje recusar tudo que
> não precisar aceitar.

Depois da recusa, `captura get` no mesmo id responde `404` — a captura sai do
caminho de leitura de vez. Mas **recusar de novo continua respondendo `204`
e saindo com código `0`**: a recusa é idempotente e nunca acusa "já
recusada", ao contrário do `get`.

Uma captura **já aceita** não pode ser recusada: `409` ("A captura não pode
ser recusada no status atual (já foi aceita ou está em processamento)"),
que o CLI classifica corretamente como `client_error` e sai com `1`.
