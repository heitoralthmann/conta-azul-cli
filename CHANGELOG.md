# Changelog

Todas as mudanças notáveis deste projeto são documentadas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/).
A política de versionamento do enum `kind` do envelope de erro está descrita
no [README](README.md#contrato-de-saída).

## [Unreleased]

Concentra a **campanha de verificação de endpoints** (2026-08-15 a
2026-08-19): os nove grupos de comandos do CLI foram exercitados contra a API
de produção, um a um, e **todos tinham pelo menos um defeito**. As entradas
abaixo foram agrupadas por tipo, não por grupo verificado; o histórico por
grupo, com as armadilhas de cada um, mora em
[`COMMANDS.md`](COMMANDS.md#notas-para-quem-for-estender).

### Added

- **Formatadores de resposta, por composição.** `ResponseFormatterInterface`
  + `FormatterRegistry`: um formato novo é uma classe e um registro em
  `FormatterRegistry::withDefaults()`. `--format=toon|json` escolhe o
  encoder; `--raw` é atalho para JSON. Comandos continuam só chamando
  `ResponseRenderer::render()`.
- **`PageSizeRule`.** Descreve a *forma* do limite de página de um endpoint —
  degraus discretos ou qualquer inteiro até o teto —, porque medir só o teto
  não descrevia `captura status`. Os demais endpoints seguem no padrão
  anterior sem mudança de comportamento.
- **Guarda local de 20 ids em `captura status`.** Acima disso a API responde
  `400` ("O campo 'ids' não pode conter mais de 20 itens"); agora o CLI
  recusa antes da ida à rede, como já fazia com o tamanho de página.
- **`parcela update`.** Expõe `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}`
  pelo que ele é — atualização parcial da parcela (nota, descrição,
  vencimento, `composicao_valor`, método de pagamento, perda, `nsu`, conta
  financeira). Escrita síncrona; `versao` obrigatório no payload.

### Changed

- **Saída padrão passou de JSON compacto para TOON.** Sucesso (stdout),
  erros e avisos (stderr) usam o mesmo formatter. JSON compacto — o
  contrato anterior — só sai com `--raw` ou `--format=json`. `--json`
  continua sendo o payload de entrada das escritas. **Mudança
  incompatível** para quem parseava stdout/stderr com `jq` sem a flag.
- **`parcela baixar` agora quita de verdade, por outro endpoint.** Passou a
  chamar `POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa`, e
  ganhou a opção obrigatória `--conta-financeira` (a API exige a conta que
  recebe a baixa). As opções `--poll-timeout` e `--no-wait` saíram: a escrita
  é síncrona e nunca devolveu protocolo. **Mudança incompatível** para quem
  usava as opções removidas.
- **Grupo `notas fiscais` verificado contra a produção (2026-08-19).** Os
  quatro comandos foram exercitados e passaram a ✅ em `COMMANDS.md`. As duas
  listagens tiveram **todos os catorze filtros provados um a um** com valor
  discriminante, depois de confirmar com `zzz_bogus=abc` que ambas descartam
  parâmetro desconhecido em silêncio: os três de `nota-fiscal list`
  (`documento_tomador`, `numero_nota`, `id_venda`) e os onze de
  `nota-fiscal-servico list` estavam **todos corretos** — o primeiro grupo da
  campanha cujo conjunto de filtros veio inteiro certo da documentação.
  `nota-fiscal get` foi exercitado nos dois formatos que devolve: XML puro para
  uma nota `EMITIDA` e ZIP (NF-e + carta de correção) para a única
  `CORRIGIDA_SUCESSO` da conta. Testes de módulo passaram a inspecionar a URL
  de saída dos catorze filtros, já que os testes de cliente não pegam nome
  errado.
- **`nota-fiscal vincular-mdfe` exercitado em produção, com autorização
  explícita do titular da conta.** Era a única escrita do CLI que jamais tinha
  retornado sucesso. O ciclo completo (`AUTORIZADO` → `ENCERRADO` →
  `CANCELADO`) foi executado sobre notas de 2024 marcadas com `identificador`
  `TESTE HEITOR`, confirmando o `204 No Content` que a documentação afirmava
  sem prova. Também foram medidos o array plural, a repetição da mesma chave, a
  reautorização depois do cancelamento e o lote misto com chave inexistente.
  O XML das quatro notas envolvidas foi conferido por SHA-256 antes e depois:
  **byte a byte idêntico** — a escrita cria metadado e não toca o documento
  fiscal.
- **Grupo `contrato` verificado contra a produção (2026-08-19).** Os seis
  comandos foram exercitados — dois contratos de teste criados, um
  encerrado e ambos excluídos, com a listagem de volta a zero e a contagem
  de vendas de volta a 6652 — e passaram a ✅ em `COMMANDS.md`. Os seis
  parâmetros de `contrato list` sobreviveram à receita completa, o terceiro
  grupo seguido **sem nenhum bug de filtro**. O que estava errado era a
  descrição de tudo o mais: a resposta da listagem é `{itens_totais,
  itens[]}` e não `items[]`; `contrato create` cobra **onze** campos
  obrigatórios, não os quatro documentados, com `numero` aninhado em
  `termos` (a mensagem de erro não diz onde) e `itens` na raiz (em `termos`
  é recusado), e `data_fim` exigido mesmo com `tipo_expiracao: NUNCA`; a
  resposta da criação é `{id, id_legado}`, sem o `id_venda` documentado; e
  a `observacoes` enviada na raiz reaparece em
  `condicao_pagamento.observacoes_pagamento`, mesma troca vista em
  `orcamento`.
- **`contrato delete` documentado como exclusão lógica, não permanente.**
  Depois do `DELETE`, `contrato get` continua respondendo `200` com
  `status: DELETADO` — só a listagem para de mostrá-lo. As vendas
  associadas, essas, são canceladas mesmo: um contrato que gerou 25 vendas
  agendadas devolveu a contagem global ao valor anterior.
- **Documentado que criar um contrato cria vendas imediatamente.** Um
  contrato mensal com `tipo_expiracao: NUNCA` gerou 25 vendas agendadas de
  uma vez, agendando dois anos à frente e ignorando o `data_fim`; com
  `tipo_expiracao: DATA` e janela de um mês, gerou 2. Vale saber antes de
  exercitar o endpoint numa conta real.
- **Documentado que `contrato get`, `delete` e `encerrar` devolvem `500`
  para um uuid válido inexistente**, em vez de `404`. Nas duas escritas o
  CLI classifica isso como `ambiguous` e sugere `ca financeiro alteracoes`,
  sugerindo reconciliar uma operação que nunca existiu. A classificação não
  foi alterada porque ela é compartilhada por todos os grupos; fica
  registrada como comportamento conhecido.
- **Grupo `orcamento` verificado contra a produção (2026-08-19).** Os
  quatro comandos foram exercitados — três orçamentos de teste criados,
  lidos e excluídos, com a listagem de volta aos 158 registros — e passaram
  a ✅ em `COMMANDS.md`. Os nove filtros de `orcamento list` sobreviveram à
  receita completa, então **nenhum bug de filtro**; as divergências foram
  outras duas, e das piores já encontradas neste projeto:
  **`total_itens` conta errado** (responde `157` junto de 158 itens
  distintos, porque ignora os orçamentos em `ORCAMENTO_RECUSADO` que a
  mesma resposta devolve — quem paginar por ele perde registros); e
  **`observacoes` e `observacoes_pagamento` trocam de lugar entre escrita e
  leitura**, confirmado com valores distintos em cada campo e
  `descricao`/`previsao_entrega` como controle. Nenhuma das duas é
  compensada no CLI, que repassa `--json` sem transformação. Documentado
  ainda: os três pares de data são tudo-ou-nada (um lado sozinho devolve
  `400`) e `--data-alteracao-*` é o único que exige data-time ISO 8601;
  `numero` é atribuído pela API, do mesmo contador das vendas; a exclusão é
  **física** (`get` passa a `404`), ao contrário de `venda excluir-lote`, e
  o `204` não distingue um uuid excluído de um inexistente; e o recurso não
  tem update nem exclusão individual — `PUT` e `DELETE` em
  `/v1/orcamentos/{id}` respondem `405`.
- **Filtros por array de `orcamento list` reavaliados.** `situacoes`,
  `numeros` e `ids_clientes` foram exercitados e **funcionam**, inclusive
  com um único valor escalar (e com valores repetidos ou separados por
  vírgula; a forma `campo[]` devolve `400`). Continuam fora da CLI, mas a
  justificativa registrada — de que o comando genérico de listagem só
  suporta filtros escalares — não era o impedimento que parecia, e o texto
  em `COMMANDS.md` foi corrigido para dizer que é decisão de escopo.
- **Grupo `venda` verificado contra a produção (2026-08-19).** Os nove
  comandos foram exercitados num CRUD completo — três vendas de teste
  criadas, uma atualizada e todas excluídas, com a listagem de volta ao
  total de 6652 no final — e passaram a ✅ em `COMMANDS.md`. **Nenhum bug
  de filtro:** `venda list` tem o maior conjunto de filtros do CLI e os
  oito sobreviveram à receita completa (baseline, `zzz_bogus=abc`, valor
  discriminante), o primeiro grupo em que isso acontece. As divergências
  estavam na escrita, e estão documentadas: `condicao_pagamento` é
  obrigatório no `create` e não constava da lista; `venda update` exige
  `versao` mas **recusa o valor `0`** que toda venda recém-criada tem, e
  ignora o número enviado (não é trava otimista, o servidor incrementa o
  próprio contador); `venda create` responde com o enum em inglês
  (`IN_PROCESS`) enquanto `venda get` responde `EM_ANDAMENTO` para a mesma
  venda; `venda vendedores` não devolve o `id_legado` prometido; `venda
  get` não traz os itens, só contagens; e `excluir-lote` é **exclusão
  lógica** — `get` e `itens` seguem em 200, `status` vira `CANCELADO` e
  `situacao` fica como estava, então só o sumiço da listagem prova a
  remoção. Registrado também que `venda list` e `venda itens` aceitam
  `--tamanho-pagina 1000`, ou seja, não são afetados pelo limite de 100 de
  `servico list` e das notas fiscais.
- **Grupo `servico` verificado contra a produção (2026-08-19).** Os cinco
  comandos foram exercitados num CRUD completo — criar, ler, atualizar e
  excluir —, com o serviço de teste removido ao final, e passaram a ✅ em
  `COMMANDS.md`. Documentadas três armadilhas confirmadas: `servico
  delete` exige o `id_servico` **inteiro** (uuid devolve 400 pedindo
  `int64`), enquanto `get`/`update` usam o uuid; a exclusão é **lógica** e
  invisível para `servico get`, que continua respondendo 200 com `status`
  `ATIVO` depois de o serviço sumir das listagens; e `servico list`
  responde `{itens[], paginacao}`, uma terceira convenção de paginação.
- **Grupo `produto` verificado contra a produção (2026-08-19).** Dez dos
  onze comandos foram exercitados num CRUD completo — criar, ler,
  atualizar, excluir, mais os cinco catálogos — com o produto de teste
  removido ao final, e passaram a ✅ em `COMMANDS.md`. O restante,
  `produto ecommerce-categorias`, continua ⚠️: responde `400` em toda
  tentativa, inclusive sem parâmetro nenhum. O path está confirmado
  (caminhos vizinhos inventados caem na rota `/v1/produtos/{id}` e
  reclamam de uuid, este cai num handler de e-commerce real), então a
  hipótese que sobra é pré-condição de conta, não erro do CLI.
- **Grupo `pessoa` verificado contra a produção (2026-08-19).** Os dez
  comandos (`list`, `create`, `get`, `legado`, `update`, `patch`,
  `ativar`, `inativar`, `excluir`, `conta-conectada`) foram exercitados
  em um CRUD completo — criar, ler, atualizar, ativar/inativar e
  excluir, com o registro de teste removido ao final — e passaram a
  ✅ em `COMMANDS.md`. Nenhuma mudança de código foi necessária: todos
  os paths e payloads já estavam corretos.

### Removed

- **`servico list --codigo`, `--ids` e `--status`.** `GET /v1/servicos`
  honra **um único filtro**. Nenhum nome testado para os outros três
  (`codigo`, `codigos`, `codigo_servico`, `sku`, `ids`, `id`, `uuid`,
  `uuids`, `id_servico`, `status`, `situacao`, `ativo`, `filtro_status`,
  …) mudou o resultado, nem com valor exclusivo de um único registro.
  Ordenação também não existe neste endpoint. Mesmo motivo do caso de
  produtos: devolviam o catálogo inteiro fingindo filtrar.
- **`produto list --ids` e `produto list --categoria-id`.** Exercitados
  contra a produção, nenhum dos dois filtrava: `GET /v1/produtos`
  responde `200` e **ignora em silêncio** todo parâmetro que não
  reconhece, então as duas opções devolviam o catálogo inteiro como se
  tudo casasse — pior que não existir. Nenhum nome alternativo
  (`id`, `uuid`, `uuids`, `produto_id`, `ids[]`, repetido, separado por
  vírgula, `id_categoria`, `categoria_uuid`, …) surtiu efeito, e um
  parâmetro propositalmente inexistente se comporta igual, o que
  confirma o descarte silencioso. Removidas em vez de continuarem
  anunciando um filtro que não filtra.

### Fixed

- **`captura status` só funcionava com um id.** O cliente mandava os ids
  juntos num parâmetro só, separados por vírgula, seguindo o `explode: false`
  do OpenAPI publicado — e é exatamente essa forma que a API recusa com
  `400` ("O valor informado para o campo 'ids' é inválido"). Ela quer o
  parâmetro repetido (`ids=a&ids=b`). Como as duas codificações são idênticas
  para **um** id, todo teste de um id só passava, e a opção que existe para
  consultar vários documentos de uma vez nunca funcionou. Varridas e
  descartadas contra a produção: vírgula, espaço, `|`, `;`, JSON e `ids[]=`.
- **`captura status --tamanho-pagina` era validado contra a regra errada, nos
  dois sentidos.** Esse endpoint aceita **qualquer inteiro de 1 a 20**, e não
  os degraus discretos (`10, 20, 50, …`) das demais listagens. Validá-lo com
  a régua dos outros deixava `--tamanho-pagina 1000` chegar na API e voltar
  `400`, e recusava localmente `15`, que a API aceita. É o único limite de
  página do CLI que nunca tinha sido medido, por exigir um id de documento
  real para chamar o endpoint.
- **Nenhuma escrita assíncrona fazia polling.** O envelope de escrita aceita
  nomeia o campo `protocolo`; o CLI lia `protocolId`, nunca encontrava, e
  devolvia o envelope `PENDING` cru como se fosse o resultado final. Na
  prática `conta-a-receber create` e `conta-a-pagar create` entregavam um
  protocolo não resolvido, e `--poll-timeout` e `--no-wait` não tinham
  efeito observável — os dois caminhos faziam a mesma coisa. Agora a escrita
  é acompanhada até o estado terminal e devolve `evento_financeiro_id`.
- **Um delete bem-sucedido reportava falha.** `cobranca delete` e
  `baixa delete` respondem `200` com corpo vazio, não `204`. Como o
  tratamento de corpo vazio dependia do status ser `204`, o parser estourava
  uma `JsonException` que escapava do `CommandExecutor` (que só captura
  `CliException`): o comando imprimia a linha de uso do Symfony em stderr e
  saía com código `1`, violando o contrato de saída, embora a exclusão
  tivesse sido aplicada. Corpo vazio agora vira `[]` em qualquer status.
- **`parcela baixar` respondia sucesso sem registrar pagamento.** Mandava
  `{valor, data}` num `PATCH` da parcela; nenhum dos dois campos existe no
  schema desse endpoint, e a API descarta campo desconhecido em silêncio
  **também no corpo da escrita**. O `409` por falta de `versao` mascarava o
  problema — o comando falhava antes de conseguir não fazer nada. Ver
  *Changed*.
- **`--json '{}'` era recusado como "JSON deve ser um objeto".**
  `json_decode('{}', true)` devolve `[]`, que `array_is_list()` considera uma
  lista. O objeto vazio agora chega na API, que é quem sabe dizer quais
  campos faltam. `[]` e listas continuam recusados localmente.
- **`--tamanho-pagina` era validado com a mesma lista larga em todo
  endpoint, e três deles não a aceitam.** `PaginationValidator` liberava
  `10, 20, 50, 100, 200, 500, 1000` para qualquer listagem, mas
  `GET /v1/servicos`, `/v1/notas-fiscais` e `/v1/notas-fiscais-servico`
  respondem `400` acima de `100` ("O tamanho da página deve ser um dos
  seguintes valores: 10, 20, 50 ou 100"). Resultado: `--tamanho-pagina 200`
  passava na validação local e voltava `400` da API — o oposto do que
  validar localmente existe para fazer. O validador agora recebe o limite do
  endpoint (`validatePageSize($size, $maxSize)`), e as três listagens
  passam `PaginationValidator::CAPPED_MAX_SIZE`; a mensagem de erro lista só
  os tamanhos que aquele endpoint aceita. Os limites foram **medidos** um a
  um contra a produção em 2026-08-19, não deduzidos da documentação: as
  outras listagens (produtos e catálogos, pessoas, vendas, itens de venda,
  orçamentos, contratos, transferências, contas a pagar/receber, categorias,
  centros de custo, contas financeiras) aceitam `1000` de verdade e ficaram
  como estavam — `captura status` foi a única que não deu para medir, por
  exigir um id de documento real. Os testes de comando constroem o cliente
  **sem nenhuma resposta enfileirada**, de modo que voltar a não passar o
  limite falha ao atingir o transporte em vez de passar em silêncio, e há o
  teste oposto em `venda list` provando que `1000` continua aceito.
- **`servico list --busca` não filtrava nada.** A API chama esse filtro de
  `busca_textual`, não `busca` (o nome que produtos e pessoas usam para a
  mesma ideia); como a listagem descarta parâmetros desconhecidos em
  silêncio, o comando devolvia os 26 serviços da conta em vez do único que
  casava. `--busca` continua sendo o nome na CLI, por consistência com os
  outros grupos, mas agora vai para a API como `busca_textual`, com teste
  de módulo travando o mapeamento.
- **`produto list --codigo` não filtrava nada.** A opção era enviada à
  API como `codigo`, mas o parâmetro aceito é `sku`; como a listagem
  descarta parâmetros desconhecidos sem erro, o comando devolvia os 420
  produtos da conta em vez do único que casava. Agora `--codigo` é
  mapeado para `sku`, e um teste de módulo trava esse mapeamento.
- **`nota-fiscal list` sem datas falhava sempre.** `GET /v1/notas-fiscais`
  limita o intervalo a **15 dias** — a mesma restrição que já era conhecida em
  `nota-fiscal-servico list` — mas o comando caía no mês corrente quando as
  datas eram omitidas, e a API respondia `400 {"error":"O período entre
  data_inicial e data_final não pode ser maior que 15 dias"}`. Ou seja,
  `ca nota-fiscal list` puro **nunca funcionou**; o defeito passou despercebido
  porque toda chamada testada até aqui informava as datas. O default agora são
  os últimos 15 dias, como na listagem de serviço, e o aviso em stderr menciona
  o teto. O limite foi medido contra a produção: 15 dias de diferença passam,
  16 respondem `400`.
- **Documentação:** `COMMANDS.md` afirmava que uma resposta `204 No
  Content` é renderizada como `{}`. Ela sai como `[]` — um corpo vazio
  (e também um `{}` vindo da API) vira array PHP vazio e volta a ser
  serializado como array. Corrigido em `nota-fiscal vincular-mdfe` e
  registrado como nota geral no contrato de saída, já que afeta todos
  os comandos que respondem `204`.

### Documented

- **Grupo `captura` verificado contra a produção** (5 comandos), encerrando a
  campanha de verificação — resta só `produto ecommerce-categorias`, que
  responde `400` sob todo parâmetro tentado. Exercitado com dois recibos em
  PDF gerados para o teste, um aceito e um recusado. `COMMANDS.md` ganhou:
  o `201` (não `200`) de `captura enviar`; o `415` com que a API recusa
  formato não suportado; o fato de que **`captura enviar` cria um fornecedor
  no cadastro de pessoas** antes de qualquer aceite, reaproveitando o
  registro em documentos do mesmo CNPJ; que os ids da Captura são uuid **v7**;
  que este grupo devolve erro num **terceiro envelope** (`{"error": …}`), com
  rota inexistente caindo no `404 page not found` em texto puro do gateway;
  que `sugestao_evento_financeiro`, declarado no OpenAPI e documentado aqui,
  **nunca vem na resposta**; que `--descricao` é escrita sem leitura
  correspondente; e as três respostas diferentes para "o recurso já mudou de
  estado" — `aceitar` duas vezes devolve `200` sem criar segundo lançamento,
  `recusar` duas vezes devolve `204` das duas, e `get` numa captura recusada
  devolve `404`. Registrado também que **`aceitar` não tem volta** (cria
  evento financeiro, que a API não deixa apagar) enquanto **`recusar` tem**:
  recusar todas as capturas tira o documento da listagem, e é o único jeito,
  já que `DELETE /v1/captura/documentos/{id}` não existe.
- **`captura status` trata zero como valor, não como ausência.** A API aceita
  `pagina=0` e `tamanho_pagina=0` com `200` e cai no default (`-1` é que
  devolve `400`, "deve ser maior ou igual a 1") — ou seja, ela valida o
  negativo e deixa o zero passar como se não tivesse sido informado. O CLI
  recusa `--tamanho-pagina 0` de propósito: aceitar em silêncio um valor que
  não faz o que foi pedido é o mesmo defeito do descarte silencioso.
- **`pessoa excluir` pode ser recusado, e a mensagem funde dois casos.** Uma
  pessoa vinculada a qualquer lançamento devolve `400` ("… já foram removidos
  anteriormente ou estão vinculados a um lançamento …"), sem dizer qual dos
  dois aconteceu. Documentado depois de esbarrar nisso ao limpar o fornecedor
  que a Captura criou: aceitar a captura deu a ele um evento financeiro e a
  exclusão deixou de ser possível. `pessoa inativar` continua funcionando.
- **Notas obsoletas de "ainda não exercitado" corrigidas.** `API_COVERAGE.md`
  ainda declarava não verificados os grupos de Notas Fiscais, Vendas,
  Orçamentos, Cobranças, `parcela list` e `financeiro saldo-inicial`, todos
  fechados durante a campanha; o mesmo texto sobrevivia nos docblocks de
  `NotasFiscaisClient`, `FinanceiroClient` e `FinanceiroClientTest`. Em
  particular, o `200` com corpo vazio de `cobranca delete`/`baixa delete`
  estava marcado como "comportamento real não verificado" no mesmo commit
  em que foi corrigido por ter sido verificado.
- **O caminho `/_bundle/open-api-docs/{slug}.json` voltou a funcionar** — via
  `fetch()` de dentro da página, já que curl leva 403 —, e por ele saiu a
  spec inteira da Captura, que não é linkada em `/aboutapis`. Com a ressalva
  registrada: essa spec **estava errada** sobre a codificação de `ids`.
- **Grupo `financeiro` verificado contra a produção** (16 comandos), fechando
  a campanha iniciada em 2026-08-15. `COMMANDS.md` ganhou o payload mínimo
  real de `conta-a-receber create` — que a documentação oficial erra em dois
  pontos —, o teto de **365 dias** não documentado de `financeiro
  saldo-inicial` e `financeiro alteracoes`, os pares de campos que trocam de
  nome entre escrita e leitura (`detalhe_valor`/`composicao_valor` →
  `valor_composicao`), os `409` e `500` onde se esperava `400`, e o aviso de
  que **a API não publica `DELETE` para evento financeiro nem para centro de
  custo** — o que se cria por lá só sai pela interface web.
- **O que `nota-fiscal vincular-mdfe` faz, e o que não dá para saber sobre
  ele.** O endpoint registra na Conta Azul que um conjunto de NF-e pertence a
  um MDF-e emitido em outro sistema; **não emite MDF-e nem transmite nada à
  SEFAZ**. Os três campos são obrigatórios (a página anterior dizia que
  `status` era opcional — era falso, e é o primeiro campo validado), o
  `identificador` é texto livre, e o enum vai em caixa-alta exata. O vínculo
  **não é legível por nenhum endpoint**: não há `GET`, a nota não muda no
  `list`, repetir a chamada nunca acusa duplicata, e por isso não existe como
  desfazer nem como conferir. Um lote com uma chave válida e outra inexistente
  devolve `404` e deixa a atomicidade **indeterminada** — documentado como
  desconhecido em vez de suposto.
- **A que serve `vincular-mdfe`, confirmado pela documentação de ajuda da
  Conta Azul.** A plataforma **não emite MDF-e nativamente** — a emissão sai
  por um parceiro externo, a LOG CT-e. Este endpoint é o caminho de volta
  dessa integração: o emissor externo avisa a Conta Azul de que certas NF-e
  foram manifestadas e em que estado o manifesto está. Isso explica o payload
  magro e a ausência de `GET` — quem chama já é o dono do dado. A
  documentação oficial descreve `status` como opcional ("também é possível
  informar o status do vínculo"); produção o exige, e o valida primeiro.
- **O vínculo trava o cancelamento da NF-e.** A Conta Azul documenta o erro
  "Há um CT-e ou MDF-e vinculado a esta nota": cancelar uma nota manifestada
  exige cancelar o manifesto antes. O artigo trata do vínculo **na SEFAZ**, e
  não ficou testado se o ERP também consulta o registro interno gravado por
  este endpoint. Não afeta as notas da verificação — o prazo de cancelamento
  de NF-e é de 24 h, e nem o extemporâneo mais generoso entre as UFs (30 dias)
  alcança notas de 2024 — mas muda a recomendação de uso: **não vincule uma
  NF-e ainda dentro do prazo de cancelamento** sem manifesto real por trás.
- **`nota-fiscal list` superconta pior que `orcamento list`.** O `total_itens`
  soma notas de todos os status, mas `itens` só traz `EMITIDA` e
  `CORRIGIDA_SUCESSO`; há janelas que devolvem `itens: []` ao lado de
  `total_itens: 3`. Numa varredura de dois anos, 147 notas devolvidas contra
  contadores bem maiores. Conte `len(itens)`. Sem nenhum resultado, a API ainda
  devolve `tamanho_pagina: 9223372036854775807`.
- **Duas armadilhas novas em "Notas para quem for estender":** um default de
  intervalo pode ser inválido para o próprio endpoint (exercite o comando sem
  argumento nenhum), e uma escrita pode não ter leitura correspondente — caso
  em que só dá para provar o que **não** mudou.
- **A marca `⚠️` do `COMMANDS.md` foi reescrita.** Ela dizia "path correto
  conforme a documentação, mas nunca exercitado", o que sugere que só a
  escrita é arriscada. Depois de dois dos três grupos verificados
  aparecerem com filtros que não filtravam nada, isso é enganoso: num
  comando `⚠️` desconfie do path, do nome do filtro, do nome do campo do
  payload, do tipo do id e do formato da resposta.
- **`COMMANDS.md` ganhou a receita de verificação de listagem** em "Notas
  para quem for estender": baseline, parâmetro de controle inexistente
  (`zzz_bogus`), valor discriminante por filtro e varredura de nomes. Junto
  com o truque de `--debug` + `log.jsonl` para ver o status HTTP real, a
  tabela de onde travar cada tipo de descoberta em teste, e o estado da
  campanha por grupo. Antes, essa seção só listava as duas armadilhas de
  path da era dos 404.
- **`CONTRIBUTING.md` passou a exigir teste de mapeamento de filtro.** Os
  testes de cliente não pegam nome de filtro errado — o cliente repassa
  qualquer chave que recebe —, então quem mexer no `$filters` de um
  `*CommandModule` precisa escrever o teste de módulo que inspeciona a URL.
- Armadilhas do grupo `pessoa` confirmadas em produção: os enums vão
  acentuados como na interface (`Física`/`Jurídica`/`Estrangeira`,
  `Cliente`/`Fornecedor`/`Transportadora`) e não em maiúsculas;
  `pessoa list` responde `{totalItems, items[]}` em camelCase, com
  `items: null` quando nada casa; `--data-criacao-*` usa `YYYY-MM-DD`
  enquanto `--data-alteracao-*` exige ISO 8601 sem timezone; `pessoa
  legado` consome o `uuid_legado`, não o `id_legado`; e `pessoa update`
  (PUT) é substituição real, exigindo `cpf` (não `documento`) mais
  `codigo`, `rg`, `data_nascimento`, `email`, `telefone_comercial`,
  `observacao`, `inscricoes[]`, `outros_contatos[]` e `enderecos[]`
  não vazios.

## [0.14.0] - 2026-08-19

### Added

- Sessão de Baixas completa: `baixa create`
  (`POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa`,
  escrita síncrona — registra data, valor, juros, multa, desconto e
  método de pagamento; uma parcela pode ter mais de uma baixa,
  pagamento parcial), `baixa list` (`GET .../parcelas/{id}/baixa`, sem
  paginação), `baixa get` (`GET .../parcelas/baixa/{id}`), `baixa
  update` (`PATCH .../parcelas/baixa/{id}`, controle de concorrência
  otimista — exige o campo `versao` atual, incrementado pela API após
  o sucesso) e `baixa delete` (`DELETE .../parcelas/baixa/{id}`,
  mesma observação de `cobranca delete`: a API documenta `200 OK` sem
  schema de corpo, não `204`). Baixas é um recurso dedicado, mais rico
  que o `PATCH` simples de `parcela baixar` (que continua funcionando
  como está). Spec OpenAPI próprio (`acquittance-apis-openapi`) que
  não aparecia linkado na página inicial do portal e nunca tinha sido
  levantado. Paths e schemas conferidos direto no OpenAPI renderizado,
  ainda não exercitados contra a API real.
- **`API_COVERAGE.md` fecha em 83/83.** Essa era a última área
  descoberta na varredura de 2026-08-19 (junto com Contratos e
  Cobranças, já resolvidas em v0.12.0/v0.13.0); todos os endpoints
  publicados no portal do desenvolvedor agora têm comando.

## [0.13.0] - 2026-08-19

### Added

- Sessão de Cobranças completa: `cobranca create`
  (`POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca`,
  escrita síncrona — gera boleto, PIX ou link de pagamento para a
  parcela de uma conta a receber), `cobranca get`
  (`GET .../contas-a-receber/cobranca/{id}`) e `cobranca delete`
  (`DELETE .../contas-a-receber/cobranca/{id}`, recomendado só para
  cobrança gerada incorretamente ou a invalidar antes do pagamento — a
  API documenta resposta `200 OK` sem schema de corpo, diferente da
  convenção `204` do resto do CLI, comportamento real ainda não
  verificado). Cobranças é um spec OpenAPI próprio
  (`charge-apis-openapi`) que não aparecia linkado na página inicial
  do portal e nunca tinha sido levantado. Paths e schemas conferidos
  direto no OpenAPI renderizado, ainda não exercitados contra a API
  real. Resta só **Baixas** como recurso dedicado
  (`acquittance-apis-openapi`) fora do CLI.

## [0.12.0] - 2026-08-19

### Added

- Comandos `contrato get` (`GET /v1/contratos/{id}`), `contrato delete`
  (`DELETE /v1/contratos/{id}`, exclusão permanente que cancela as
  vendas associadas — contratos em reajuste de valor não podem ser
  removidos) e `contrato encerrar` (`POST /v1/contratos/{id}/encerrar`,
  sem corpo, desativa o contrato sem excluí-lo), completando a sessão
  de Contratos. O levantamento original de `API_COVERAGE.md` só tinha
  encontrado 3 dos 6 endpoints do spec real — o nome interno da rota
  no portal (`open-api-scheduled-sales`) não aparece linkado na página
  `/aboutapis`. Paths e schemas conferidos direto no OpenAPI
  renderizado, ainda não exercitados contra a API real.

### Changed

- `API_COVERAGE.md`/`COMMANDS.md` passam a documentar explicitamente
  duas famílias inteiras que seguem fora do CLI — **Cobranças**
  (`charge-apis-openapi`, boleto/PIX sobre contas a receber) e
  **Baixas** como recurso dedicado (`acquittance-apis-openapi`, mais
  rico que `parcela baixar`) — descobertas na mesma varredura que
  achou o gap de Contratos.

## [0.11.0] - 2026-08-18

### Added

- Comando `centro-de-custo create` (`POST /v1/centro-de-custo`),
  completando a sessão de Centros de custo. Escrita síncrona (`nome`
  obrigatório, `codigo` opcional; a resposta já traz o centro de custo
  criado, sem protocolo). Era o único endpoint que faltava em todo o
  `API_COVERAGE.md` — os 72 endpoints publicados no portal têm comando
  agora. Path e schema conferidos direto no OpenAPI renderizado (o
  portal bloqueia `WebFetch`), ainda não exercitado contra a API real.

## [0.10.0] - 2026-08-18

### Added

- Sessão de Captura (Developer Platform) completa: `captura enviar`
  (`POST /v1/captura/documentos`, multipart/form-data — `arquivo`
  obrigatório, PDF/JPEG/PNG/BMP até 10 MB, `descricao` opcional),
  `captura status` (`GET /v1/captura/documentos/status`, até 20 ids
  separados por vírgula), `captura get` (`GET /v1/captura/{id}`,
  dados extraídos pela IA), `captura aceitar`
  (`POST /v1/captura/{id}`, sem corpo, cria o evento financeiro a
  partir da prévia) e `captura recusar` (`DELETE /v1/captura/{id}`,
  sem corpo, resposta `204 No Content`). Era a última área listada
  como "fora do escopo" em `API_COVERAGE.md` (71/72 endpoints agora
  implementados — falta só criar centro de custo). Primeiro comando do
  CLI a enviar um corpo `multipart/form-data`; nenhuma mudança foi
  necessária no transporte HTTP, já que o Symfony HttpClient monta o
  multipart sozinho a partir de um resource de arquivo. Paths e
  schemas conferidos direto no OpenAPI renderizado (o portal bloqueia
  `WebFetch`), ainda não exercitados contra a API real.

## [0.9.0] - 2026-08-18

### Added

- Sessão de Orçamentos completa: `orcamento list` (`GET /v1/orcamentos`,
  filtros opcionais — mesmo recorte de `venda list`, a API não exige
  intervalo de datas), `orcamento create` (`POST /v1/orcamentos`),
  `orcamento get` (`GET /v1/orcamentos/{id}`) e `orcamento excluir-lote`
  (`DELETE /v1/orcamentos`, até 10 uuids por chamada, resposta
  `204 No Content`). Os filtros de array do endpoint de listagem
  (`ids_vendedores`, `ids_clientes`, `situacoes`, `numeros` etc.) ficam
  de fora, mesma lacuna de `venda list`: o comando genérico de listagem
  só suporta filtros escalares hoje. Paths e schemas conferidos direto
  no OpenAPI renderizado (o portal bloqueia `WebFetch`), ainda não
  exercitados contra a API real.

## [0.8.0] - 2026-08-18

### Added

- Sessão de Vendas completa: `venda list` (`GET /v1/venda/busca`,
  filtros opcionais — diferente de `contrato list`, a API não exige
  intervalo de datas), `venda create` (`POST /v1/venda`), `venda get`
  e `venda update` (`GET`/`PUT /v1/venda/{id}` — a API não expõe
  `PATCH` para vendas), `venda imprimir`
  (`GET /v1/venda/{id}/imprimir`, PDF binário devolvido em base64,
  mesmo tratamento de `nota-fiscal get`), `venda itens`
  (`GET /v1/venda/{id_venda}/itens`), `venda vendedores`
  (`GET /v1/venda/vendedores`, não pagina), `venda proximo-numero`
  (`GET /v1/venda/proximo-numero`, inteiro solto ou `null`, mesmo
  formato de `contrato proximo-numero`) e `venda excluir-lote`
  (`POST /v1/venda/exclusao-lote`, até 10 uuids por chamada). Paths e
  schemas conferidos direto na documentação renderizada (o portal
  bloqueia `WebFetch`), ainda não exercitados contra a API real.

## [0.7.0] - 2026-08-18

### Added

- Sessão de Notas Fiscais completa: `nota-fiscal list`
  (`GET /v1/notas-fiscais`, NFe de produto), `nota-fiscal get`
  (`GET /v1/notas-fiscais/{chave}`), `nota-fiscal vincular-mdfe`
  (`POST /v1/notas-fiscais/vinculo-mdfe`) e `nota-fiscal-servico list`
  (`GET /v1/notas-fiscais-servico`, NFS-e de serviço). Paths e schemas
  conferidos direto na documentação renderizada (o portal bloqueia
  `WebFetch`), ainda não exercitados contra a API real.
- `ApiTransportInterface::requestBinary()`, um caminho de transporte
  dedicado para respostas que não são JSON. `nota-fiscal get` devolve o
  XML da NF-e (ou um ZIP, quando há carta de correção) em vez de JSON; o
  conteúdo cru quebraria tanto `request()` quanto `requestScalar()`
  (ambos tentam decodificar JSON). O comando embrulha o binário em base64
  dentro do envelope de sempre, preservando o contrato de stdout só-JSON.
- `PeriodoPadrao::inicioUltimos15Dias()`/`hoje()`. Diferente dos demais
  endpoints com intervalo obrigatório, `nota-fiscal-servico list` limita
  o intervalo a 15 dias — usar o default de "mês corrente" dos outros
  comandos estouraria esse limite quase sempre.

## [0.6.0] - 2026-08-18

### Added

- Sessão de Contratos completa: `contrato list` (`GET /v1/contratos`),
  `contrato create` (`POST /v1/contratos`) e `contrato proximo-numero`
  (`GET /v1/contratos/proximo-numero`). A criação é uma escrita síncrona —
  diferente das escritas financeiras, não devolve protocolo. A consulta do
  próximo número devolve um inteiro solto no corpo da resposta, não um
  objeto; o transporte HTTP ganhou um caminho de decodificação dedicado a
  esse formato (`ApiTransportInterface::requestScalar`).

## [0.5.0] - 2026-08-18

### Added

- Comando `financeiro saldo-inicial`, completando a sessão de Eventos
  financeiros / diversos (`GET /v1/financeiro/eventos-financeiros/saldo-inicial`).

## [0.4.0] - 2026-08-18

### Added

- Comando `parcela list`, completando a sessão de Parcelas
  (`GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas`). O
  endpoint não pagina: devolve o array completo de parcelas do evento.

## [0.3.0] - 2026-08-18

### Added

- Comando `transferencia list`, cobrindo o endpoint da sessão de
  Transferências (`GET /v1/financeiro/transferencias`).

## [0.2.0] - 2026-08-18

### Added

- Comandos `categoria configuracao-padrao` e `categoria dre`, cobrindo os
  dois endpoints restantes da sessão de Categorias
  (`GET /v1/categorias/configuracao-padrao` e
  `GET /v1/financeiro/categorias-dre`).
- Testes de comando com `CommandTester` cobrindo exit code, stdout e stderr
  para todos os comandos-folha exceto `auth login` (depende de um listener
  TCP real, sem transporte injetável).
- `.gitattributes` com `export-ignore` para arquivos de desenvolvimento.
- `CONTRIBUTING.md` e `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1).
- Dependabot para `composer` e `github-actions`.
- Cobertura de testes medida com `pcov` no CI (sem gate).
- Arquivo `VERSION` como fonte única da versão do CLI.
- Templates de issue (bug e feature request) e de pull request.
- `shipmonk/composer-dependency-analyser` para detectar dependência não
  usada ou usada sem estar declarada.
- Badges no README (Tests, PHP Version, License) e um parágrafo em inglês
  no topo para descoberta internacional.
- PHPStan passou a analisar `tests/` em level 6, além de `src/` em level max.

### Changed

- CI passou a rodar `composer lint` em vez de invocar `phpcs` diretamente,
  o que passa a cobrir `bin/ca` (antes ignorado silenciosamente).
- `ci.yml` fatiado em `tests.yml`, `static-analysis.yml`, `code-style.yml`,
  `security.yml` (com cron semanal) e `coverage.yml`.
- Actions do GitHub fixadas por SHA de commit em todos os workflows.
- `ext-ctype` e `symfony/http-client-contracts` passaram a ser dependências
  diretas — eram usadas mas resolvidas só transitivamente.
- Piso de PHP subiu para `^8.4` e `symfony/console`, `symfony/dotenv` e
  `symfony/http-client` para `^8.0` (resolve para 8.1.x atual;
  `symfony/http-client-contracts` permanece em `^3.0`, sem release v4). CI:
  `tests.yml` perdeu 8.3 da matriz (agora `8.4`/`8.5`); `static-analysis.yml`,
  `code-style.yml`, `coverage.yml`, `mutation-testing.yml`, `security.yml` e
  `release.yml`, antes fixados em 8.3, passaram para 8.4. README e badge
  atualizados para "PHP 8.4+". O Console 8 renomeou
  `Application::add()` para `addCommand()`; `addCommands()` (usado pelo
  bootstrap em `ContaAzulApplication`) segue existindo e chama o método novo
  internamente, sem impacto — só o helper de teste `CommandTestCase`, que
  chamava `add()` diretamente, precisou de ajuste.
- 21 dos 26 comandos-folha que estendiam `Command` diretamente com nome e
  descrição estáticos migraram para o estilo invokable do Console
  (`__invoke()` + `#[Argument]`/`#[Option]`, sem `configure()`/`execute()`).
  `Pessoa\BatchCommand` e as quatro classes reutilizáveis
  `Support/Resource*Command` continuam no estilo clássico: seus
  nomes/descrições são resolvidos em runtime pelos módulos que as
  instanciam, e `#[AsCommand]` exige valores estáticos.
- `Configuration` trocou os 13 pares `private readonly`/getter por
  propriedades `public readonly`, no mesmo estilo já usado por `TokenData`
  e `CliException`.
- `ProtocolPoller` passou a comparar o status do protocolo contra o novo
  enum `ProtocolStatus` em vez de comparar strings soltas
  (`'SUCCESS'`/`'ERROR'`) diretamente.
- `Pessoa\BatchCommand` passou a receber um `BatchOperation` (enum) em vez
  de uma string literal `'activate'|'deactivate'|'delete'`.
- `BaseClient`, já documentada como fachada legada sem uso fora dos
  próprios testes, ganhou o atributo nativo `#[\Deprecated]` — no
  construtor, já que PHP não permite aplicá-lo à declaração da classe.

### Removed

- `sistema_agentes_ia.html`, arquivo sem relação com o CLI versionado na raiz.
- `api-drift.yml`: sempre gravava `drift=false` (a detecção estava
  inteiramente comentada, esperando uma URL de spec que a Conta Azul não
  publica) e lia como proteção ativa sem ser. Disciplina de checagem virou
  processo manual, documentado no `CONTRIBUTING.md`.

## [0.1.0] - 2026-08-16

Primeira versão tagueada.

### Added

- LICENSE (Apache 2.0).
- `SECURITY.md`, apontando para o GitHub Private Vulnerability Reporting.
- `composer validate --strict` e `composer audit` no CI.
- Matriz de PHP no CI expandida para 8.3, 8.4 e 8.5.

### Changed

- Pacote renomeado de `contaazul-cli/cli` para `heitoralthmann/conta-azul-cli`,
  com aviso de não-oficialidade adicionado ao README.
- `release.yml` corrigido: faltava `permissions: contents: write`, o que
  impedia a publicação do PHAR na release do GitHub.

[Unreleased]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.14.0...HEAD
[0.14.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.13.0...v0.14.0
[0.13.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.12.0...v0.13.0
[0.12.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.11.0...v0.12.0
[0.11.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/heitoralthmann/conta-azul-cli/releases/tag/v0.1.0
