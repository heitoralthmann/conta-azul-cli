# Changelog

Todas as mudanças notáveis deste projeto são documentadas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/).
A política de versionamento do enum `kind` do envelope de erro está descrita
no [README](README.md#contrato-de-saída).

## [Unreleased]

### Added

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

### Removed

- `sistema_agentes_ia.html`, arquivo sem relação com o CLI versionado na raiz.
- `api-drift.yml`: sempre gravava `drift=false` (a detecção estava
  inteiramente comentada, esperando uma URL de spec que a Conta Azul não
  publica) e lia como proteção ativa sem ser. Disciplina de checagem virou
  processo manual, documentado no `CONTRIBUTING.md`.

### Evaluated

- Migrar para `symfony/console ^8.0`: **não viável agora**. A partir da
  v8.0.0 os pacotes Symfony exigem PHP 8.4+, e este projeto declara
  `php: ^8.3` — migrar hoje derrubaria o suporte a PHP 8.3. `^7.0` já
  resolve para a última minor (7.4.x) automaticamente, então não há
  necessidade de mudar o constraint por enquanto. Revisitar quando o piso
  de PHP subir para 8.4.

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

[Unreleased]: https://github.com/heitoralthmann/conta-azul-cli/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/heitoralthmann/conta-azul-cli/releases/tag/v0.1.0
