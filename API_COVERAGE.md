# Cobertura da API Conta Azul

Arquivo de controle: todos os endpoints publicados no [Portal do Desenvolvedor Conta Azul](https://developers.contaazul.com/aboutapis), agrupados por área funcional, com o que o `ca` já implementa marcado.

**Escopo do CLI.** O `ca` cobre a família **Financeiro** (Finanças + Baixas + Cobranças), o recurso de **Protocolos** que ela depende para escritas assíncronas, as APIs de **Pessoas**, **Produtos**, **Serviços** e **Contratos**. As demais áreas (Notas Fiscais, Vendas, Orçamentos, Captura) estão listadas por completude.

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

4 endpoints. Fora do escopo do CLI (NFS-e ainda "em breve" na própria API, por ora só produtos/NFe).

- [ ] `GET /v1/notas-fiscais` — buscar notas fiscais de produtos
- [ ] `GET /v1/notas-fiscais/{chave}` — buscar nota fiscal por chave
- [ ] `GET /v1/notas-fiscais-servico` — buscar notas fiscais de serviço
- [ ] `POST /v1/notas-fiscais/vinculo-mdfe` — vincular MDF-e

## 🛒 Vendas

9 endpoints. Fora do escopo do CLI.

- [ ] `GET /v1/venda/busca` — buscar vendas por filtro
- [ ] `POST /v1/venda` — criar venda
- [ ] `GET /v1/venda/{id}` — buscar venda por id
- [ ] `PUT /v1/venda/{id}` — atualizar venda
- [ ] `GET /v1/venda/{id}/imprimir` — PDF da venda
- [ ] `GET /v1/venda/{id_venda}/itens` — itens de uma venda
- [ ] `GET /v1/venda/vendedores` — listar vendedores
- [ ] `GET /v1/venda/proximo-numero`
- [ ] `POST /v1/venda/exclusao-lote` — excluir vendas em lote

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
| Notas Fiscais | 0 | 4 |
| Vendas | 0 | 9 |
| Orçamentos | 0 | 4 |
| Captura | 0 | 5 |
| **Total** | **48** | **72** |
