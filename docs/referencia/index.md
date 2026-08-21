<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Referência de comandos

Todos os comandos do `ca`, agrupados pelo endpoint que consomem.

Cada endpoint traz uma marca de confiança:

- **✅ verificado** — exercitado contra a API real e respondeu como documentado.
- **⚠️ não verificado** — escrito a partir da documentação e nunca exercitado.
  Não leia como "provavelmente certo": leia como **não confiável**.

!!! danger "O que `⚠️` realmente significa"

    A marca não quer dizer só "a escrita nunca foi disparada". Ela quer
    dizer **não confiável em todos os eixos**: path, nome de filtro, nome
    de campo do payload, tipo do id e formato da resposta. Os filtros
    listados numa seção `⚠️` saíram da documentação, não de uma chamada
    real.

    Isso não é pessimismo de ofício: a campanha de verificação exercitou
    os nove grupos do CLI contra a produção e **todos tinham pelo menos um
    defeito** — filtros que devolviam a coleção inteira fingindo filtrar,
    campos obrigatórios que a documentação não lista, um comando apontado
    para o endpoint errado, e um caso que passava em qualquer teste
    razoável. O apanhado está em
    [Notas para quem for estender](../guia/estendendo.md), e é leitura
    obrigatória antes de mexer em qualquer integração.

!!! info "Esta página é gerada"

    A tabela sai das definições do Symfony Console, então ela não pode
    divergir do que o CLI realmente aceita. A prosa de cada grupo é
    curada em `docs/_data/commands/`. Convenção de leitura: `obrig.` marca
    o que falha sem valor; `padrão` é o que o CLI assume quando você
    omite.

| Comando | Endpoint | |
|---|---|---|
| [`config path`](config.md#config-path) | `— (local)` | ✅ |
| [`config init`](config.md#config-init) | `— (local)` | ✅ |
| [`config set`](config.md#config-set) | `— (local)` | ✅ |
| [`config show`](config.md#config-show) | `— (local)` | ✅ |
| [`auth login`](auth.md#auth-login) | `— (local)` | ✅ |
| [`auth logout`](auth.md#auth-logout) | `— (local)` | ✅ |
| [`categoria list`](categoria.md#categoria-list) | `GET /v1/categorias` | ✅ |
| [`categoria configuracao-padrao`](categoria.md#categoria-configuracao-padrao) | `GET /v1/categorias/configuracao-padrao` | ✅ |
| [`categoria dre`](categoria.md#categoria-dre) | `GET /v1/financeiro/categorias-dre` | ✅ |
| [`centro-de-custo list`](centro-de-custo.md#centro-de-custo-list) | `GET /v1/centro-de-custo` | ✅ |
| [`centro-de-custo create`](centro-de-custo.md#centro-de-custo-create) | `POST /v1/centro-de-custo` | ✅ |
| [`conta-financeira list`](conta-financeira.md#conta-financeira-list) | `GET /v1/conta-financeira` | ✅ |
| [`conta-financeira saldo`](conta-financeira.md#conta-financeira-saldo) | `GET /v1/conta-financeira/{id}/saldo-atual` | ✅ |
| [`transferencia list`](transferencia.md#transferencia-list) | `GET /v1/financeiro/transferencias` | ✅ |
| [`conta-a-receber list`](conta-a-receber.md#conta-a-receber-list) | `GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar` | ✅ |
| [`conta-a-receber create`](conta-a-receber.md#conta-a-receber-create) | `POST /v1/financeiro/eventos-financeiros/contas-a-receber` | ✅ |
| [`cobranca create`](cobranca.md#cobranca-create) | `POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca` | ✅ |
| [`cobranca get`](cobranca.md#cobranca-get) | `GET /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` | ✅ |
| [`cobranca delete`](cobranca.md#cobranca-delete) | `DELETE /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` | ✅ |
| [`conta-a-pagar list`](conta-a-pagar.md#conta-a-pagar-list) | `GET /v1/financeiro/eventos-financeiros/contas-a-pagar/buscar` | ✅ |
| [`conta-a-pagar create`](conta-a-pagar.md#conta-a-pagar-create) | `POST /v1/financeiro/eventos-financeiros/contas-a-pagar` | ✅ |
| [`parcela get`](parcela.md#parcela-get) | `GET /v1/financeiro/eventos-financeiros/parcelas/{id}` | ✅ |
| [`parcela update`](parcela.md#parcela-update) | `PATCH /v1/financeiro/eventos-financeiros/parcelas/{id}` | ✅ |
| [`parcela baixar`](parcela.md#parcela-baixar) | `POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` | ✅ |
| [`parcela list`](parcela.md#parcela-list) | `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas` | ✅ |
| [`baixa create`](baixa.md#baixa-create) | `POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` | ✅ |
| [`baixa list`](baixa.md#baixa-list) | `GET /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` | ✅ |
| [`baixa get`](baixa.md#baixa-get) | `GET /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ✅ |
| [`baixa update`](baixa.md#baixa-update) | `PATCH /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ✅ |
| [`baixa delete`](baixa.md#baixa-delete) | `DELETE /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` | ✅ |
| [`financeiro alteracoes`](financeiro.md#financeiro-alteracoes) | `ISO 8601 **sem timezone**` | ✅ |
| [`financeiro saldo-inicial`](financeiro.md#financeiro-saldo-inicial) | `GET /v1/financeiro/eventos-financeiros/saldo-inicial` | ✅ |
| [`protocolo get`](protocolo.md#protocolo-get) | `GET /v1/protocolo/{id}` | ✅ |
| [`contrato list`](contrato.md#contrato-list) | `GET /v1/contratos` | ✅ |
| [`contrato create`](contrato.md#contrato-create) | `POST /v1/contratos` | ✅ |
| [`contrato proximo-numero`](contrato.md#contrato-proximo-numero) | `GET /v1/contratos/proximo-numero` | ✅ |
| [`contrato get`](contrato.md#contrato-get) | `GET /v1/contratos/{id}` | ✅ |
| [`contrato delete`](contrato.md#contrato-delete) | `DELETE /v1/contratos/{id}` | ✅ |
| [`contrato encerrar`](contrato.md#contrato-encerrar) | `POST /v1/contratos/{id}/encerrar` | ✅ |
| [`pessoa list`](pessoa.md#pessoa-list) | `GET /v1/pessoas` | ✅ |
| [`pessoa create`](pessoa.md#pessoa-create) | `POST /v1/pessoas` | ✅ |
| [`pessoa get`](pessoa.md#pessoa-get) | `GET /v1/pessoas/{id}` | ✅ |
| [`pessoa legado`](pessoa.md#pessoa-legado) | `GET /v1/pessoas/legado/{id}` | ✅ |
| [`pessoa update`](pessoa.md#pessoa-update) | `PUT /v1/pessoas/{id}` | ✅ |
| [`pessoa patch`](pessoa.md#pessoa-patch) | `PATCH /v1/pessoas/{id}` | ✅ |
| [`pessoa ativar`](pessoa.md#pessoa-ativar) | `POST /v1/pessoas/ativar` | ✅ |
| [`pessoa inativar`](pessoa.md#pessoa-inativar) | `POST /v1/pessoas/inativar` | ✅ |
| [`pessoa excluir`](pessoa.md#pessoa-excluir) | `POST /v1/pessoas/excluir` | ✅ |
| [`pessoa conta-conectada`](pessoa.md#pessoa-conta-conectada) | `GET /v1/pessoas/conta-conectada` | ✅ |
| [`produto list`](produto.md#produto-list) | `GET /v1/produtos` | ✅ |
| [`produto create`](produto.md#produto-create) | `POST /v1/produtos` | ✅ |
| [`produto get`](produto.md#produto-get) | `GET /v1/produtos/{id}` | ✅ |
| [`produto delete`](produto.md#produto-delete) | `DELETE /v1/produtos/{id}` | ✅ |
| [`produto update`](produto.md#produto-update) | `PATCH /v1/produtos/{id}` | ✅ |
| [`produto categorias`](produto.md#produto-categorias) | `GET /v1/produtos/categorias` | ✅ |
| [`produto cest`](produto.md#produto-cest) | `GET /v1/produtos/cest` | ✅ |
| [`produto ncm`](produto.md#produto-ncm) | `GET /v1/produtos/ncm` | ✅ |
| [`produto unidades-medida`](produto.md#produto-unidades-medida) | `GET /v1/produtos/unidades-medida` | ✅ |
| [`produto ecommerce-marcas`](produto.md#produto-ecommerce-marcas) | `GET /v1/produtos/ecommerce-marcas` | ✅ |
| [`produto ecommerce-categorias`](produto.md#produto-ecommerce-categorias) | `GET /v1/produtos/ecommerce-categorias` | ⚠️ |
| [`servico list`](servico.md#servico-list) | `GET /v1/servicos` | ✅ |
| [`servico create`](servico.md#servico-create) | `POST /v1/servicos` | ✅ |
| [`servico get`](servico.md#servico-get) | `GET /v1/servicos/{id}` | ✅ |
| [`servico update`](servico.md#servico-update) | `PATCH /v1/servicos/{id}` | ✅ |
| [`servico delete`](servico.md#servico-delete) | `DELETE /v1/servicos` | ✅ |
| [`nota-fiscal list`](nota-fiscal.md#nota-fiscal-list) | `GET /v1/notas-fiscais` | ✅ |
| [`nota-fiscal get`](nota-fiscal.md#nota-fiscal-get) | `GET /v1/notas-fiscais/{chave}` | ✅ |
| [`nota-fiscal vincular-mdfe`](nota-fiscal.md#nota-fiscal-vincular-mdfe) | `POST /v1/notas-fiscais/vinculo-mdfe` | ✅ |
| [`nota-fiscal-servico list`](nota-fiscal.md#nota-fiscal-servico-list) | `GET /v1/notas-fiscais-servico` | ✅ |
| [`venda list`](venda.md#venda-list) | `GET /v1/venda/busca` | ✅ |
| [`venda create`](venda.md#venda-create) | `POST /v1/venda` | ✅ |
| [`venda get`](venda.md#venda-get) | `GET /v1/venda/{id}` | ✅ |
| [`venda update`](venda.md#venda-update) | `PUT /v1/venda/{id}` | ✅ |
| [`venda imprimir`](venda.md#venda-imprimir) | `GET /v1/venda/{id}/imprimir` | ✅ |
| [`venda itens`](venda.md#venda-itens) | `GET /v1/venda/{id_venda}/itens` | ✅ |
| [`venda vendedores`](venda.md#venda-vendedores) | `GET /v1/venda/vendedores` | ✅ |
| [`venda proximo-numero`](venda.md#venda-proximo-numero) | `GET /v1/venda/proximo-numero` | ✅ |
| [`venda excluir-lote`](venda.md#venda-excluir-lote) | `POST /v1/venda/exclusao-lote` | ✅ |
| [`orcamento list`](orcamento.md#orcamento-list) | `GET /v1/orcamentos` | ✅ |
| [`orcamento create`](orcamento.md#orcamento-create) | `POST /v1/orcamentos` | ✅ |
| [`orcamento get`](orcamento.md#orcamento-get) | `GET /v1/orcamentos/{id}` | ✅ |
| [`orcamento excluir-lote`](orcamento.md#orcamento-excluir-lote) | `DELETE /v1/orcamentos` | ✅ |
| [`captura enviar`](captura.md#captura-enviar) | `POST /v1/captura/documentos` | ✅ |
| [`captura status`](captura.md#captura-status) | `GET /v1/captura/documentos/status` | ✅ |
| [`captura get`](captura.md#captura-get) | `GET /v1/captura/{id}` | ✅ |
| [`captura aceitar`](captura.md#captura-aceitar) | `POST /v1/captura/{id}` | ✅ |
| [`captura recusar`](captura.md#captura-recusar) | `DELETE /v1/captura/{id}` | ✅ |
