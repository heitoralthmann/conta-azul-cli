# Cobertura da API Conta Azul

Arquivo de controle: todos os endpoints publicados no [Portal do Desenvolvedor Conta Azul](https://developers.contaazul.com/aboutapis), agrupados por área funcional, com o que o `ca` já implementa marcado.

**Escopo do CLI.** O `ca` cobre a família **Financeiro** (Finanças + Cobranças + Baixas), o recurso de **Protocolos** que ela depende para escritas assíncronas, e as APIs de **Pessoas**, **Produtos**, **Serviços**, **Contratos**, **Notas Fiscais**, **Vendas**, **Orçamentos** e **Captura**. Todos os 83 endpoints publicados no portal têm comando.

Levantado em 2026-08-15 navegando a documentação (portal bloqueia `WebFetch`); referência cruzada com `COMMANDS.md`, `src/Api/FinanceiroClient.php`, `src/Api/PessoasClient.php`, `src/Api/ProdutosClient.php` e `src/Api/ServicosClient.php`. Ao adicionar um comando novo, marque o endpoint correspondente nesta lista no mesmo commit.

**2026-08-19: o levantamento original ficou incompleto.** Uma varredura direta dos specs OpenAPI reais (não só da página `/aboutapis`) achou 3 áreas com endpoints nunca listados aqui, todas corrigidas na mesma sequência de sessões: **Contratos** tinha só 3 dos 6 endpoints do spec (`contrato get`/`delete`/`encerrar`); **Cobranças** (`charge-apis-openapi`) e **Baixas** (`acquittance-apis-openapi`) eram specs OpenAPI próprios que não aparecem linkados na página inicial e não tinham nenhum comando (`cobranca create`/`get`/`delete`; `baixa create`/`list`/`get`/`update`/`delete`). Como sempre, "todos os endpoints publicados" é o que foi ativamente verificado navegando o portal — não há garantia formal contra a Conta Azul publicar algo novo sem aviso.

---

## 🔐 Autenticação

Fluxo Authorization Code (OAuth2). Implementado em `src/Auth/`.

- [x] Autorizar — redireciona para login (`CA_AUTHORIZE_URL`, navegador)
- [x] Trocar código por `access_token` — `POST /oauth2/token` (`grant_type=authorization_code`)
- [x] Renovar `access_token` — `POST /oauth2/token` (`grant_type=refresh_token`)

## 💰 Financeiro / Cobranças / Baixas

25 endpoints: 17 do spec `financial-apis-openapi` (o núcleo de Financeiro) + 3 do spec `charge-apis-openapi` (Cobranças) + 5 do spec `acquittance-apis-openapi` (Baixas). `src/Api/FinanceiroClient.php`.
Levantado em 2026-08-15; `transferencia list` acrescentado e validado contra a API real em 2026-08-18;
`parcela list` acrescentado em 2026-08-18 (path e schema conferidos direto na doc, endpoint ainda não exercitado contra a API real);
`financeiro saldo-inicial` acrescentado em 2026-08-18, completando a sessão (path e query params conferidos direto na doc, endpoint ainda não exercitado contra a API real);
`centro-de-custo create` acrescentado em 2026-08-18 e exercitado em produção em 2026-08-19. Atenção: a API **não publica `DELETE`** para centro de custo nem para evento financeiro (contas a receber/pagar) — o que se cria por esses três endpoints só sai pela interface web.
**Cobranças e Baixas são specs OpenAPI próprios** (`charge-apis-openapi`, `acquittance-apis-openapi`), descobertos em 2026-08-19 — não estavam linkados na página `/aboutapis` e nunca tinham sido levantados. Ambos implementados na sequência (`cobranca create`/`get`/`delete`; `baixa create`/`list`/`get`/`update`/`delete`), completando a cobertura da API inteira.

### Centros de custo
- [x] `GET /v1/centro-de-custo` — `centro-de-custo list`
- [x] `POST /v1/centro-de-custo` — `centro-de-custo create`; escrita síncrona (`nome` obrigatório, `codigo` opcional), sem protocolo

### Categorias
- [x] `GET /v1/categorias` — `categoria list`
- [x] `GET /v1/categorias/configuracao-padrao` — `categoria configuracao-padrao`
- [x] `GET /v1/financeiro/categorias-dre` — `categoria dre`

### Contas financeiras
- [x] `GET /v1/conta-financeira` — `conta-financeira list`
- [x] `GET /v1/conta-financeira/{id_conta_financeira}/saldo-atual` — `conta-financeira saldo`

### Transferências
- [x] `GET /v1/financeiro/transferencias` — `transferencia list`

### Contas a receber
- [x] `POST /v1/financeiro/eventos-financeiros/contas-a-receber` — `conta-a-receber create`
- [x] `GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar` — `conta-a-receber list`

### Cobranças (spec `charge-apis-openapi`)
Gera cobrança (boleto/PIX/link de pagamento) para a parcela de uma conta a receber. Path e schema conferidos direto no OpenAPI renderizado (https://developers.contaazul.com/docs/charge-apis-openapi/v1); ainda não exercitados contra a API real. O DELETE documenta resposta `200 OK` sem schema de corpo — diferente da convenção `204` do resto do CLI; comportamento real não verificado.
- [x] `POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca` — `cobranca create`; escrita síncrona, sem protocolo
- [x] `GET /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id_cobranca}` — `cobranca get`
- [x] `DELETE /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id_cobranca}` — `cobranca delete`

### Contas a pagar
- [x] `POST /v1/financeiro/eventos-financeiros/contas-a-pagar` — `conta-a-pagar create`
- [x] `GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar` — `conta-a-pagar list`

### Parcelas
- [x] `GET /v1/financeiro/eventos-financeiros/parcelas/{id}` — `parcela get`
- [x] `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` — `parcela update`; atualiza a parcela (**não** quita), síncrono, `versao` obrigatório
- [x] `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas` — `parcela list`

### Baixas (spec `acquittance-apis-openapi`)
Recurso dedicado de baixa (quitação): registra data, valor, juros, multa, desconto e método de pagamento; uma parcela pode ter mais de uma baixa (pagamento parcial, confirmado em produção). `baixa update` exige o campo `versao` atual no payload — controle de concorrência otimista, a API o incrementa após o sucesso, e sem ele a resposta é `409`. Exercitados contra a API real em 2026-08-19; o DELETE responde mesmo `200 OK` sem corpo, e a exclusão desfaz a quitação.
- [x] `POST /v1/financeiro/eventos-financeiros/parcelas/{parcela_id}/baixa` — `baixa create` e `parcela baixar` (atalho com `--valor`/`--data`/`--conta-financeira`); escrita síncrona, sem protocolo
- [x] `GET /v1/financeiro/eventos-financeiros/parcelas/{parcela_id}/baixa` — `baixa list`; não pagina
- [x] `GET /v1/financeiro/eventos-financeiros/parcelas/baixa/{baixa_id}` — `baixa get`
- [x] `PATCH /v1/financeiro/eventos-financeiros/parcelas/baixa/{baixa_id}` — `baixa update`
- [x] `DELETE /v1/financeiro/eventos-financeiros/parcelas/baixa/{baixa_id}` — `baixa delete`

### Eventos financeiros / diversos
- [x] `GET /v1/financeiro/eventos-financeiros/alteracoes` — `financeiro alteracoes`
- [x] `GET /v1/financeiro/eventos-financeiros/saldo-inicial` — `financeiro saldo-inicial`

## 🧾 Protocolos

1 endpoint. `src/Api/FinanceiroClient.php::getProtocolo`.

- [x] `GET /v1/protocolo/{id}` — `protocolo get`

## 📑 Contratos

6 endpoints. `src/Api/ContratosClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto na
documentação renderizada (https://developers.contaazul.com/docs/contracts-apis-openapi/v1),
não deduzidos do PDF/YAML — mas, diferente da sessão Financeiro, ainda não
exercitados contra a API real.
`contrato get`, `contrato delete` e `contrato encerrar` acrescentados em
2026-08-19: o levantamento original de 2026-08-18 só tinha visto 3 dos 6
endpoints do spec — o nome interno da rota no portal é
`open-api-scheduled-sales` (não tem relação com "vendas agendadas"; mesma
armadilha de `open-api-proposal` = Orçamentos), e não aparece linkado na
página `/aboutapis`, só dentro do próprio bundle OpenAPI.

- [x] `GET /v1/contratos` — `contrato list`; exige `data_inicio`/`data_fim`
- [x] `POST /v1/contratos` — `contrato create`; escrita síncrona (não devolve protocolo)
- [x] `GET /v1/contratos/{id}` — `contrato get`
- [x] `DELETE /v1/contratos/{id}` — `contrato delete`; exclusão permanente, cancela vendas associadas; contratos em reajuste de valor não podem ser removidos; resposta `204 No Content`
- [x] `POST /v1/contratos/{id}/encerrar` — `contrato encerrar`; sem corpo, desativa o contrato (para de gerar cobranças); contratos em reajuste de valor não podem ser encerrados; resposta `204 No Content`
- [x] `GET /v1/contratos/proximo-numero` — `contrato proximo-numero`; corpo da resposta é um inteiro solto (ou `null`), não um objeto

## 👥 Pessoas / Fornecedores

10 endpoints. `src/Api/PessoasClient.php`.

- [x] `GET /v1/pessoas` — `pessoa list`
- [x] `POST /v1/pessoas` — `pessoa create`
- [x] `GET /v1/pessoas/{id}` — `pessoa get`
- [x] `PUT /v1/pessoas/{id}` — `pessoa update`
- [x] `PATCH /v1/pessoas/{id}` — `pessoa patch`
- [x] `GET /v1/pessoas/legado/{id}` — `pessoa legado`
- [x] `POST /v1/pessoas/ativar` — `pessoa ativar`
- [x] `POST /v1/pessoas/inativar` — `pessoa inativar`
- [x] `POST /v1/pessoas/excluir` — `pessoa excluir`
- [x] `GET /v1/pessoas/conta-conectada` — `pessoa conta-conectada`

## 📦 Produtos e Serviços — Produtos

11 endpoints. `src/Api/ProdutosClient.php`.

- [x] `GET /v1/produtos` — `produto list`
- [x] `POST /v1/produtos` — `produto create`
- [x] `GET /v1/produtos/{id}` — `produto get`
- [x] `PATCH /v1/produtos/{id}` — `produto update`
- [x] `DELETE /v1/produtos/{id}` — `produto delete`
- [x] `GET /v1/produtos/categorias` — `produto categorias`
- [x] `GET /v1/produtos/cest` — `produto cest`
- [x] `GET /v1/produtos/ncm` — `produto ncm`
- [x] `GET /v1/produtos/unidades-medida` — `produto unidades-medida`
- [x] `GET /v1/produtos/ecommerce-categorias` — `produto ecommerce-categorias`
- [x] `GET /v1/produtos/ecommerce-marcas` — `produto ecommerce-marcas`

## 📦 Produtos e Serviços — Serviços

5 endpoints. `src/Api/ServicosClient.php`.

- [x] `GET /v1/servicos` — `servico list`
- [x] `POST /v1/servicos` — `servico create`
- [x] `GET /v1/servicos/{id}` — `servico get`
- [x] `PATCH /v1/servicos/{id}` — `servico update`
- [x] `DELETE /v1/servicos` — `servico delete`

## 🧮 Notas Fiscais

4 endpoints. `src/Api/NotasFiscaisClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto na
documentação renderizada (https://developers.contaazul.com/open-api-docs/open-api-invoice/v1),
já que o portal bloqueia `WebFetch`/`curl` — ainda não exercitados contra a
API real. A API só suporta consulta (NFe de produto e NFS-e de serviço) e
vínculo a MDF-e; não há emissão.

- [x] `GET /v1/notas-fiscais` — `nota-fiscal list`; exige `data_inicial`/`data_final`; retorna só NFe EMITIDA e CORRIGIDA_SUCESSO
- [x] `GET /v1/notas-fiscais/{chave}` — `nota-fiscal get`; resposta binária (XML ou ZIP), devolvida em base64 para preservar o contrato de stdout em JSON
- [x] `GET /v1/notas-fiscais-servico` — `nota-fiscal-servico list`; exige `data_competencia_de`/`data_competencia_ate`, com **máximo de 15 dias** de intervalo
- [x] `POST /v1/notas-fiscais/vinculo-mdfe` — `nota-fiscal vincular-mdfe`; escrita síncrona, resposta `204 No Content`

## 🛒 Vendas

9 endpoints. `src/Api/VendasClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto na
documentação renderizada (https://developers.contaazul.com/docs/sales-apis-openapi/v1),
já que o portal bloqueia `WebFetch`/`curl` — ainda não exercitados contra a
API real.

- [x] `GET /v1/venda/busca` — `venda list`; filtros opcionais (sem intervalo de datas obrigatório)
- [x] `POST /v1/venda` — `venda create`
- [x] `GET /v1/venda/{id}` — `venda get`; aceita uuid ou id legado
- [x] `PUT /v1/venda/{id}` — `venda update`; a API não expõe PATCH para vendas
- [x] `GET /v1/venda/{id}/imprimir` — `venda imprimir`; resposta binária (PDF), devolvida em base64 para preservar o contrato de stdout em JSON
- [x] `GET /v1/venda/{id_venda}/itens` — `venda itens`
- [x] `GET /v1/venda/vendedores` — `venda vendedores`; não pagina
- [x] `GET /v1/venda/proximo-numero` — `venda proximo-numero`; corpo da resposta é um inteiro solto (ou `null`), não um objeto
- [x] `POST /v1/venda/exclusao-lote` — `venda excluir-lote`; aceita de 1 a 10 uuids por chamada

## 📋 Orçamentos

4 endpoints. `src/Api/OrcamentosClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto no
OpenAPI renderizado (https://developers.contaazul.com/docs/open-api-proposal),
já que o portal bloqueia `WebFetch`/`curl` — ainda não exercitados contra a
API real. `orcamento list` só expõe os filtros escalares do endpoint
(mesmo recorte de `venda list`); os filtros de array (`ids_vendedores`,
`ids_clientes`, `ids_natureza_operacao`, `ids_categorias`, `ids_produtos`,
`situacoes`, `origens`, `numeros`, `ids_legado_*`) ficam de fora porque
`ResourceListCommand`/`PaginationOptions` só suportam opções escalares hoje.

- [x] `GET /v1/orcamentos` — `orcamento list`; filtros opcionais (sem intervalo de datas obrigatório)
- [x] `POST /v1/orcamentos` — `orcamento create`
- [x] `DELETE /v1/orcamentos` — `orcamento excluir-lote`; aceita de 1 a 10 uuids por chamada, resposta `204 No Content`
- [x] `GET /v1/orcamentos/{id}` — `orcamento get`

## 📥 Captura (Developer Platform)

5 endpoints. `src/Api/CapturaClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto no
OpenAPI renderizado
(https://developers.contaazul.com/open-api-docs/developer-platform-open-api-capture/v1),
via Chrome (`_bundle/open-api-docs/developer-platform-open-api-capture.json`),
já que o portal bloqueia `WebFetch`/`curl` — ainda não exercitada contra a
API real. Fluxo: `captura enviar` sobe o arquivo e devolve `id` (do
documento); `captura status` consulta esse `id` e devolve, quando pronta, a
`id_captura`; `captura get` traz a prévia extraída pela IA para essa
`id_captura`; `captura aceitar`/`captura recusar` decidem o que fazer com a
prévia.

- [x] `POST /v1/captura/documentos` — `captura enviar`; multipart/form-data (`arquivo` obrigatório — PDF/JPEG/PNG/BMP até 10 MB —, `descricao` opcional)
- [x] `GET /v1/captura/documentos/status` — `captura status`; `ids` aceita até 20 valores separados por vírgula
- [x] `GET /v1/captura/{id}` — `captura get`; busca os dados extraídos por `id_captura`
- [x] `POST /v1/captura/{id}` — `captura aceitar`; sem corpo, cria o evento financeiro a partir da prévia
- [x] `DELETE /v1/captura/{id}` — `captura recusar`; sem corpo, resposta `204 No Content`

---

## Resumo

| Área | Implementados | Total |
|---|---|---|
| Autenticação | 3 | 3 |
| Financeiro / Cobranças / Baixas | 25 | 25 |
| Protocolos | 1 | 1 |
| Contratos | 6 | 6 |
| Pessoas / Fornecedores | 10 | 10 |
| Produtos | 11 | 11 |
| Serviços | 5 | 5 |
| Notas Fiscais | 4 | 4 |
| Vendas | 9 | 9 |
| Orçamentos | 4 | 4 |
| Captura | 5 | 5 |
| **Total** | **83** | **83** |
