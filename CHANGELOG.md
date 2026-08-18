# Changelog

Todas as mudanças notáveis deste projeto são documentadas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/).
A política de versionamento do enum `kind` do envelope de erro está descrita
no [README](README.md#contrato-de-saída).

## [Unreleased]

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

[Unreleased]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.8.0...HEAD
[0.8.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/heitoralthmann/conta-azul-cli/releases/tag/v0.1.0
