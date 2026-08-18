# Cobertura da API Conta Azul

Arquivo de controle: todos os endpoints publicados no [Portal do Desenvolvedor Conta Azul](https://developers.contaazul.com/aboutapis), agrupados por área funcional, com o que o `ca` já implementa marcado.

**Escopo do CLI.** O `ca` cobre a família **Financeiro** (Finanças + Baixas + Cobranças), o recurso de **Protocolos** que ela depende para escritas assíncronas, as APIs de **Pessoas**, **Produtos**, **Serviços**, **Contratos**, **Notas Fiscais** e **Vendas**. As demais áreas (Orçamentos, Captura) estão listadas por completude.

Levantado em 2026-08-15 navegando a documentação (portal bloqueia `WebFetch`); referência cruzada com `COMMANDS.md`, `src/Api/FinanceiroClient.php`, `src/Api/PessoasClient.php`, `src/Api/ProdutosClient.php` e `src/Api/ServicosClient.php`. Ao adicionar um comando novo, marque o endpoint correspondente nesta lista no mesmo commit.

---

## 🔐 Autenticação

Fluxo Authorization Code (OAuth2). Implementado em `src/Auth/`.

- [x] Autorizar — redireciona para login (`CA_AUTHORIZE_URL`, navegador)
- [x] Trocar código por `access_token` — `POST /oauth2/token` (`grant_type=authorization_code`)
- [x] Renovar `access_token` — `POST /oauth2/token` (`grant_type=refresh_token`)

## 💰 Financeiro / Cobranças / Baixas

17 endpoints. `src/Api/FinanceiroClient.php`.
Levantado em 2026-08-15; `transferencia list` acrescentado e validado contra a API real em 2026-08-18;
`parcela list` acrescentado em 2026-08-18 (path e schema conferidos direto na doc, endpoint ainda não exercitado contra a API real);
`financeiro saldo-inicial` acrescentado em 2026-08-18, completando a sessão (path e query params conferidos direto na doc, endpoint ainda não exercitado contra a API real).

### Centros de custo
- [x] `GET /v1/centro-de-custo` — `centro-de-custo list`
- [ ] `POST /v1/centro-de-custo` — criar centro de custo

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

### Contas a pagar
- [x] `POST /v1/financeiro/eventos-financeiros/contas-a-pagar` — `conta-a-pagar create`
- [x] `GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar` — `conta-a-pagar list`

### Parcelas
- [x] `GET /v1/financeiro/eventos-financeiros/parcelas/{id}` — `parcela get`
- [x] `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` — `parcela baixar`
- [x] `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas` — `parcela list`

### Eventos financeiros / diversos
- [x] `GET /v1/financeiro/eventos-financeiros/alteracoes` — `financeiro alteracoes`
- [x] `GET /v1/financeiro/eventos-financeiros/saldo-inicial` — `financeiro saldo-inicial`

## 🧾 Protocolos

1 endpoint. `src/Api/FinanceiroClient.php::getProtocolo`.

- [x] `GET /v1/protocolo/{id}` — `protocolo get`

## 📑 Contratos

3 endpoints. `src/Api/ContratosClient.php`.
Sessão implementada em 2026-08-18; paths e schemas conferidos direto na
documentação renderizada (https://developers.contaazul.com/docs/contracts-apis-openapi/v1),
não deduzidos do PDF/YAML — mas, diferente da sessão Financeiro, ainda não
exercitados contra a API real.

- [x] `GET /v1/contratos` — `contrato list`; exige `data_inicio`/`data_fim`
- [x] `POST /v1/contratos` — `contrato create`; escrita síncrona (não devolve protocolo)
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

4 endpoints. Fora do escopo do CLI.

- [ ] `GET /v1/orcamentos` — buscar orçamentos por filtro
- [ ] `POST /v1/orcamentos` — criar orçamento
- [ ] `DELETE /v1/orcamentos` — excluir orçamentos em lote
- [ ] `GET /v1/orcamentos/{id}` — buscar orçamento por id

## 📥 Captura (Developer Platform)

5 endpoints. Fora do escopo do CLI.

- [ ] `POST /v1/captura/documentos` — enviar documento para captura
- [ ] `GET /v1/captura/documentos/status` — status de captura por documento
- [ ] `GET /v1/captura/{id}` — buscar captura por id
- [ ] `POST /v1/captura/{id}`
- [ ] `DELETE /v1/captura/{id}` — excluir captura

---

## Resumo

| Área | Implementados | Total |
|---|---|---|
| Autenticação | 3 | 3 |
| Financeiro / Cobranças / Baixas | 15 | 17 |
| Protocolos | 1 | 1 |
| Contratos | 3 | 3 |
| Pessoas / Fornecedores | 10 | 10 |
| Produtos | 11 | 11 |
| Serviços | 5 | 5 |
| Notas Fiscais | 4 | 4 |
| Vendas | 9 | 9 |
| Orçamentos | 0 | 4 |
| Captura | 0 | 5 |
| **Total** | **61** | **72** |
