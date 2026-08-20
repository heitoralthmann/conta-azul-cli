<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Notas fiscais

A API só suporta **consulta** (NFe de produto emitida e NFS-e de serviço) e vínculo a MDF-e — não há emissão pelo CLI.

**Verificado contra produção em 2026-08-19** — os quatro comandos, incluindo o
ciclo completo de `vincular-mdfe` (`AUTORIZADO` → `ENCERRADO` → `CANCELADO`)
sobre notas de 2024 marcadas com o `identificador` de teste
`TESTE CLI CONTA AZUL`.

> **As duas listagens limitam o intervalo a 15 dias**, não só a de serviço.
> `nota-fiscal list` respondia `400` em *toda* invocação sem datas, porque o
> default era o mês corrente. Medido: 15 dias de diferença passam, 16 respondem
> `{"error":"O período entre data_inicial e data_final não pode ser maior que
> 15 dias"}`. Corrigido — o default agora são os últimos 15 dias, como na NFS-e.

## `nota-fiscal list` ✅ { #nota-fiscal-list }

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

## `nota-fiscal get` ✅ { #nota-fiscal-get }

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

## `nota-fiscal vincular-mdfe` ✅ { #nota-fiscal-vincular-mdfe }

`POST /v1/notas-fiscais/vinculo-mdfe` — **escrita síncrona**, resposta `204 No Content` (renderizada como `[]`).

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do vínculo |

Registra na Conta Azul que um conjunto de notas fiscais pertence a um MDF-e
(Manifesto Eletrônico de Documentos Fiscais, modelo 58) emitido em outro
sistema. **Não emite MDF-e e não transmite nada à SEFAZ** — o payload não tem
veículo, motorista nem percurso, que a SEFAZ exigiria.

> **Para que este endpoint existe.** A Conta Azul [**não emite MDF-e
> nativamente**](https://ajuda.contaazul.com/hc/pt-br/articles/42794589052301-Notas-fiscais-a-Conta-Azul-emite-CT-e-ou-MDF-e);
> a emissão é feita por um parceiro externo, a **LOG CT-e**, contratada à
> parte e conectada em *Integrações > Conecte-se por um parceiro > Emissão
> fiscal e Obrigações*. Este endpoint é o **caminho de volta** dessa
> integração: quem emitiu o manifesto lá fora avisa a Conta Azul de que
> aquelas NF-e foram manifestadas, e em que estado o manifesto está. Isso
> explica o formato do payload — chaves, um identificador opaco e o estado —
> e explica por que não há `GET`: quem chama já é o dono do dado.


Campos do payload — **os três são obrigatórios**:

| Campo | Tipo | Observação |
|---|---|---|
| `chaves_acesso` | array de string | Chaves de acesso das NF-e. Aceita mais de uma |
| `identificador` | string | **Texto livre** — não é validado como chave de MDF-e |
| `status` | string | `AUTORIZADO`, `ENCERRADO` ou `CANCELADO`, caixa-alta exata |

> **`status` é obrigatório**, ao contrário do que esta página afirmava até
> 2026-08-19. Sem ele a API responde `400` com a lista de valores aceitos, e
> ela valida esse campo **antes** dos demais — por isso ele é o primeiro erro
> que aparece, mesmo faltando os outros dois. O erro não era desta página: a
> própria documentação oficial descreve o campo como "também é possível
> informar o status do vínculo". Produção discorda.

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

> **O vínculo tem uma consequência conhecida: ele trava o cancelamento da
> NF-e.** A Conta Azul [documenta o erro "Há um CT-e ou MDF-e vinculado a esta
> nota"](https://ajuda.contaazul.com/hc/pt-br/articles/115007937188-NF-e-Erro-H%C3%A1-um-CT-e-ou-MDF-e-vinculado-a-esta-nota):
> para cancelar uma NF-e manifestada, o MDF-e precisa ser cancelado antes e o
> cancelamento processado pela SEFAZ. O artigo trata do vínculo **que existe na
> SEFAZ**, não do registro interno que este endpoint grava — se o ERP também
> consulta o registro interno antes de deixar cancelar, não foi testado.
>
> Na prática isso **não afeta as notas usadas na verificação**, e não por
> sorte de estado: o prazo de cancelamento de NF-e é de 24 h da autorização, e
> mesmo o cancelamento extemporâneo mais generoso entre as UFs (30 dias, no
> RJ) expirou há muito para notas de 2024. Uma nota velha não é cancelável por
> ninguém, com ou sem manifesto. Todas as notas tocadas ficaram, além disso,
> em estado `CANCELADO` — que é justamente o estado que destravaria o
> cancelamento, se ele ainda fosse possível.
>
> Para uso real, a ordem importa: **não vincule uma NF-e que ainda esteja
> dentro do prazo de cancelamento** sem que o manifesto exista de fato.

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

## `nota-fiscal-servico list` ✅ { #nota-fiscal-servico-list }

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
| `--numero-nfse-final` | não | — | Número final da NFS-e |
| `--numero-rps-inicial` / `--numero-rps-final` | não | — | Intervalo de número do RPS |
| `--numero-rps-final` | não | — | Número final do RPS |
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
