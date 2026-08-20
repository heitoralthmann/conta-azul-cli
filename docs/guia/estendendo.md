# Notas para quem for estender

Todos os 83 endpoints publicados no portal já têm comando — veja a
referência rápida no topo deste arquivo e [cobertura da API](../desenvolvimento/cobertura-da-api.md) para a lista
completa por área (Contratos, Notas Fiscais, Vendas, Orçamentos, Captura,
Cobranças e Baixas foram implementados além do escopo original declarado
em [especificação](../desenvolvimento/especificacao.md); as duas últimas descobertas só em 2026-08-19,
porque vivem em specs OpenAPI próprios sem link na página inicial do
portal).

Recursos que **não existem** na API v1 — não procure o comando, não há endpoint:

**lançamentos**, busca por id de conta a pagar/receber, update/delete desses eventos, e criação de transferência.

> Comandos para esses recursos já existiram, apontando para paths inventados, e retornavam 404. Foram removidos em vez de continuarem anunciando o que não funciona. Um comando `cobrança` também já existiu nessa situação e foi removido — não confundir com os comandos reais de hoje, `cobranca create`/`get`/`delete`, que usam o path correto (`.../contas-a-receber/gerar-cobranca` e `.../contas-a-receber/cobranca/{id}`), descoberto na sessão de 2026-08-19.

## A API falha em silêncio mais do que falha em voz alta

**A armadilha mais cara deste projeto:** as listagens respondem `200` e
**descartam sem avisar** qualquer parâmetro de query que não reconheçam. Um
filtro com o nome errado não devolve `400` — devolve **a coleção inteira**,
que parece um resultado legítimo. Foi assim que `produto list --codigo`
(mandava `codigo`, a API quer `sku`) e três dos quatro filtros de
`servico list` passaram despercebidos por várias versões.

Comprovado com um parâmetro propositalmente inexistente (`zzz_bogus=abc`),
que se comporta exatamente como os nomes errados que o CLI mandava.

## Receita para verificar uma listagem

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
5. **Se o filtro aceita vários valores, teste com dois.** Não é só o nome
   que pode estar errado: a *codificação* também. `captura status` manda
   `ids`, e a vírgula que o OpenAPI declara devolve `400` — a API quer o
   parâmetro repetido. Com **um** valor as duas formas coincidem, então o
   defeito sobrevive a todo teste de um valor só.
6. **Rode o comando sem argumento nenhum.** Um default que o próprio
   endpoint recusa é invisível para qualquer teste que passe valores
   explícitos: `nota-fiscal list` falhava `400` em 100% das chamadas sem
   datas, e ninguém tinha notado porque quem chamava sempre passava datas.

O mesmo ceticismo vale para escrita: **a API valida um campo por vez**, então
cada `400` revela só o *próximo* campo faltante. Descobrir o payload de um
`PUT` é iterativo — veja a tabela de `pessoa update`, montada assim.

## Ver o status HTTP real

O stdout não expõe o código de status, e `204` vs `200` importa (um corpo
vazio sai como `[]`, não `{}`). Use:

```sh
./bin/ca <comando> --debug …
tail -5 ~/.cache/conta-azul-cli/log.jsonl
```

## Armadilhas já confirmadas

Vinte e cinco divergências entre a documentação e a API real, todas
medidas exercitando. Agrupadas pelo tipo de coisa em que você vai
esbarrar, não pelo grupo de comandos onde apareceram — a armadilha de um
grupo costuma reaparecer em outro.

### Onde o recurso mora e como ele se chama

Nada aqui se deduz: nem o path, nem o nome, nem o tipo do id.

1. **O segmento `/financeiro/` só existe em parte dos recursos.** Categorias, centros de custo e contas financeiras ficam na raiz da `v1`.
2. **A nomenclatura alterna plural e singular:** `categorias`, mas `centro-de-custo` e `conta-financeira`.
3. **Um comando pode apontar para o endpoint errado e nunca dar sinal disso.** `parcela baixar` era um `PATCH` na parcela porque alguém concluiu que "não existe subrecurso /baixar". Existe: `POST /parcelas/{id}/baixa`. O endpoint que ele chamava é real, responde `200` e serve para outra coisa — atualizar a parcela. Confira o *propósito* do endpoint na documentação, não só se ele responde.
4. **O mesmo registro tem dois ids, e comandos do mesmo grupo usam ids diferentes.** `servico get`/`update` querem o uuid; `servico delete` quer o `id_servico` inteiro.
5. **Nem todo id da API é uuid v4.** Os ids da Captura são uuid **v7** (`01a01a96-61f5-7007-…`). Uma validação local de formato que exigisse v4 recusaria id legítimo.
6. **Leitura e escrita usam nomes diferentes para o mesmo dado.** `pessoa` lê `documento` e escreve `cpf`; o SKU de um produto é `codigo` na listagem, `codigo_sku` no detalhe e `sku` na query.

### O que a API engole sem reclamar

A família do descarte silencioso — todo caso em que um `200` esconde que o que você mandou foi ignorado.

7. **O descarte silencioso não é só da query: vale para o corpo da escrita.** `parcela baixar` mandava `{valor, data}` num `PATCH` que não tem nenhum dos dois campos. Resposta: `200`, `versao` incrementada e **nenhum pagamento registrado**. O mesmo vale para `centro-de-custo create`, onde um `zzz_bogus` no payload produz exatamente o mesmo erro que um campo real ausente — ou seja, **não dá para provar que um campo opcional existe mandando ele junto de um payload inválido**. Só a escrita bem-sucedida seguida de leitura prova.
8. **A codificação de um parâmetro de lista também precisa ser exercitada.** `captura status` manda `ids`, e o OpenAPI publicado declara `explode: false` (vírgula). A API responde `400` a essa forma: ela quer `ids` **repetido**. Com um item só as duas formas são idênticas — então o defeito sobrevive a qualquer teste de um id só, que é exatamente o teste que se escreve primeiro. Ao integrar um parâmetro que aceita vários valores, **teste com dois**.
9. **A regra de `tamanho_pagina` pode não ser uma lista de valores.** Todas as listagens medidas até então recusavam qualquer coisa fora de `10, 20, 50, …`; `captura status` aceita **qualquer inteiro de 1 a 20**. Validar contra os degraus discretos errava nos dois sentidos ao mesmo tempo — deixava passar `1000` (que a API recusa) e recusava `15` (que ela aceita). Meça a *forma* do limite, não só o teto: mande `15` e veja se passa.
10. **O default de um intervalo de datas pode ser inválido para o próprio endpoint.** `nota-fiscal list` limita a janela a 15 dias, mas o CLI mandava o mês corrente — então o comando sem argumentos falhava com `400` em 100% das vezes, e ninguém notou porque quem chamava sempre passava datas. Ao adicionar um default, exercite-o **sem argumento nenhum**.

### O que ela exige na escrita

Descobrir um payload é iterativo, pelo motivo explicado na receita acima. O que esta lista acrescenta é *onde* procurar quando o nome óbvio não resolve.

11. **Enums vão acentuados e capitalizados como na interface** (`Física`, `Cliente`), não em `SCREAMING_SNAKE_CASE`.
12. **Um campo obrigatório pode estar aninhado onde você não procuraria.** O número do contrato é `termos.numero`, e a mensagem de erro ("O número do contrato é obrigatório") não diz onde. Se um nome óbvio não resolve, tente dentro de cada sub-objeto do payload antes de concluir que o nome está errado.
13. **Zero pode ser lido como ausente.** `venda update` exige `versao` e recusa `0` com "campo obrigatório" — justamente o valor que uma venda recém-criada tem.
14. **Nem todo campo obrigatório vira `400`.** Falta de `versao` responde **`409`** em `baixa update` e `parcela update`. E em `cobranca create` um payload incompleto responde **`500`**, que o CLI classifica como `ambiguous` e manda reconciliar — reconcilie mesmo: na verificação, nada tinha sido criado.

### O que ela devolve

Formato, contador e código de status divergiram da documentação em quase todo grupo.

15. **A resposta da escrita não fala a mesma língua que a da leitura.** `venda create` devolve `situacao.nome` em inglês (`IN_PROCESS`) para a venda que `venda get` mostra como `EM_ANDAMENTO`.
16. **Dois campos podem estar simplesmente trocados.** O que `orcamento create` recebe em `observacoes` volta em `observacoes_pagamento` no `orcamento get`, e vice-versa; em `contrato create` a `observacoes` da raiz reaparece em `condicao_pagamento.observacoes_pagamento`. Escreva um valor distinto em cada campo suspeito e leia de volta: é a única forma de enxergar isso.
17. **O total de uma listagem pode não bater com o que ela devolve.** `orcamento list` responde `total_itens: 157` junto de 158 itens, porque o contador ignora `ORCAMENTO_RECUSADO`. Em `nota-fiscal list` a divergência chega a 100%: o contador soma todos os status, mas só `EMITIDA` e `CORRIGIDA_SUCESSO` vêm em `itens` — há janelas que devolvem `itens: []` com `total_itens: 3`.
18. **Um id inexistente nem sempre é `404`.** Em `contrato get`, `delete` e `encerrar`, um uuid válido que não existe devolve `500` — e nas escritas o CLI traduz isso para `ambiguous`, sugerindo reconciliar algo que nunca aconteceu.
19. **`200` com corpo vazio não é a mesma coisa que `204`.** `cobranca delete` e `baixa delete` respondem `200` sem corpo. Enquanto o CLI tratava o caso vazio só para `204`, o parser estourava, a exceção escapava do `CommandExecutor` (que só pega `CliException`) e **um delete bem-sucedido imprimia a linha de uso do Symfony e saía com código `1`**. Ao integrar um delete, confirme o status *e* o corpo.
20. **Um endpoint pode ter data de corte que a documentação não menciona.** `financeiro saldo-inicial` e `financeiro alteracoes` recusam intervalo maior que **365 dias**. Como o default do CLI é o mês corrente, nenhum teste que passa datas explícitas curtas encontra isso.

### O que fica para trás

Antes de escrever qualquer coisa num grupo novo, saiba se existe caminho de volta.

21. **Exclusão não quer dizer a mesma coisa em todo grupo.** `produto delete` faz o `get` passar a 404; `servico delete` é lógico e o `get` continua respondendo 200 com `status` `ATIVO`; `venda excluir-lote` também é lógico e vira `status` `CANCELADO` — mas deixa `situacao` como estava.
22. **Uma leitura pode escrever em outro cadastro.** `captura enviar` sobe um arquivo — e, ao terminar a extração, o fornecedor identificado **já existe** no cadastro de pessoas, com `criado_em` do dia. Nenhum aceite foi dado ainda. Ao mapear o efeito colateral de um comando, não pare no recurso que ele nomeia.
23. **Uma escrita pode não ter leitura correspondente.** `nota-fiscal vincular-mdfe` grava um vínculo que nenhum `GET` devolve, que não altera a nota e que repetir nunca acusa duplicata. Sem `GET`, sem efeito colateral observável e sem erro de duplicata, não há como provar a limpeza — só dá para provar o que **não** mudou (o SHA-256 do XML da nota, idêntico antes e depois). Quando um grupo tiver escrita sem leitura, decida antes até onde vale exercitar.
24. **Escrever é fácil; desfazer é que pode não existir.** A API não publica `DELETE` para evento financeiro (contas a receber/pagar) nem para centro de custo — `DELETE`, `PUT` e `PATCH` nesses paths devolvem o `404` genérico de rota inexistente. Antes de criar registro de teste num grupo, **verifique se existe caminho de volta**. Para distinguir "rota não existe" de "id não existe", compare a mensagem: rota inexistente devolve `"message":"Not Found"`; rota real com id desconhecido devolve `"message":"O recurso solicitado não foi encontrado"`. Em `captura` nem essa comparação serve: lá a rota inexistente devolve `404 page not found` em **texto puro**, e as reais devolvem `{"error": "…"}` — um terceiro envelope de erro.
25. **Idempotência varia entre operações irmãs.** Em `captura`, aceitar duas vezes devolve `200` e não cria segundo lançamento; recusar duas vezes devolve `204` das duas; mas `get` numa captura recusada devolve `404`. Três respostas diferentes para "o recurso já saiu do estado que você esperava".

## Onde travar o que você descobrir

| O que | Onde |
|---|---|
| Path e método de um endpoint | `tests/Unit/Api/*ClientTest.php` |
| Mapeamento opção da CLI → parâmetro de query | `tests/Integration/Command/Module/*CommandModuleTest.php` |
| Filtro removido por não existir | idem, com asserção negativa (`assertFalse(hasOption(...))`) |

O segundo caso é o que pegou os bugs de 2026-08-19 e **não existia antes
deles** — o teste de cliente passava feliz, porque o cliente repassa
qualquer filtro que recebe. Se você mexer em `$filters` num
`*CommandModule`, escreva o teste de mapeamento junto.

## Estado da verificação

| Grupo | Situação |
|---|---|
| Leituras financeiras | ✅ verificadas em 2026-08-15 |
| `pessoa` | ✅ 10/10 (2026-08-19) — nenhum bug de código |
| `produto` | ✅ 10/11 (2026-08-19) — 1 filtro errado, 2 inexistentes; falta `ecommerce-categorias` |
| `servico` | ✅ 5/5 (2026-08-19) — 1 filtro errado, 3 inexistentes |
| `venda` | ✅ 9/9 (2026-08-19) — nenhum bug de filtro; as armadilhas estavam na escrita |
| `orcamento` | ✅ 4/4 (2026-08-19) — nenhum bug de filtro; `total_itens` conta errado e dois campos trocam de nome entre escrita e leitura |
| `contrato` | ✅ 6/6 (2026-08-19) — nenhum bug de filtro; payload de criação bem maior que o documentado e `delete` é lógico, não permanente |
| `notas fiscais` | ✅ 4/4 (2026-08-19) — default de data que o próprio endpoint recusava |
| `financeiro` | ✅ 16/16 (2026-08-19) — o grupo com mais defeitos de código da campanha: polling que nunca acontecia, delete que reportava falha ao dar certo, e um comando apontando para o endpoint errado |
| `captura` | ✅ 5/5 (2026-08-19) — a codificação de `ids` que o OpenAPI declara é recusada pela API, e o único limite de página que não é lista de valores |

Falta um comando: `produto ecommerce-categorias`, que responde `400` sob todo
parâmetro tentado, inclusive sem nenhum. O path está confirmado, então a
hipótese que sobra é pré-condição de conta — não erro do CLI.

**O que a campanha ensinou, em uma frase: o risco muda de lugar, não some.**
Os quatro primeiros grupos erraram em **nome de filtro**. Os quatro do meio
(`venda`, `orcamento`, `contrato`, `notas fiscais`) não tinham um único
filtro errado e erraram em **payload de escrita e formato de resposta** —
campo obrigatório não documentado, campo aninhado em lugar inesperado,
contador que não conta, dois pares de campos que trocam de lugar. `financeiro`
mostrou que o defeito também mora no **código do CLI**, não só nos nomes que
ele manda. E `captura` mostrou o pior tipo: um defeito que **passa em
qualquer teste razoável**, porque a codificação errada de `ids` é
indistinguível da certa quando há um id só.

Por isso a receita acima não é opcional, e por isso nenhum grupo saiu da
campanha ileso. Ao verificar o próximo endpoint que aparecer, não pergunte
"onde é provável que esteja errado" — o histórico diz que é onde você não
está olhando.

## Onde achar a documentação da API

Lista autoritativa de operações:
https://developers.contaazul.com/docs/financial-apis-openapi/v1 — o portal
bloqueia `curl` e fetch automatizado (403), então abra no navegador. Só três
specs aparecem linkadas em `/aboutapis` (financial, sales, contracts);
produtos, serviços e pessoas **não têm spec pública encontrável** e só se
descobrem exercitando.

Para uma página de operação, o caminho é
`/docs/{slug}/v1` → link da operação → `…/v1/{operationId}`, que traz o
schema completo e um `curl` de exemplo.

O caminho `/_bundle/open-api-docs/{slug}.json` **voltou a funcionar** — pelo
menos para `developer-platform-open-api-capture`, cuja spec inteira saiu por
ele em 2026-08-19, com os schemas que a página renderizada só mostra clicando
em "Show N properties". O truque é buscá-lo **de dentro da página**, com
`fetch()` no console do navegador: como curl leva 403, a requisição precisa
sair da origem já autenticada. A spec da Captura não é linkada em
`/aboutapis`; a URL renderizada dela é
`/open-api-docs/developer-platform-open-api-capture/v1`.

E vale o aviso de sempre: **essa spec estava errada**. Ela declara `ids` com
`explode: false`, forma que a própria API recusa com `400`. Ter a spec em
JSON acelera a descoberta; não substitui exercitar.

Ver também: [introdução](../index.md) para instalação, configuração e OAuth; `docs/financial-apis-openapi.yaml` para o inventário de endpoints usado na detecção de drift.
