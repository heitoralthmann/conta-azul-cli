# Changelog

Todas as mudanças notáveis deste projeto são documentadas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e este projeto segue [Semantic Versioning](https://semver.org/lang/pt-BR/).
A política de versionamento do enum `kind` do envelope de erro está descrita
no [README](README.md#contrato-de-saída).

## [Unreleased]

### Added

- Testes de comando com `CommandTester` cobrindo exit code, stdout e stderr.
- `.gitattributes` com `export-ignore` para arquivos de desenvolvimento.
- `CONTRIBUTING.md`.
- Dependabot para `composer` e `github-actions`.
- Cobertura de testes medida com `pcov` no CI (sem gate).
- Arquivo `VERSION` como fonte única da versão do CLI.

### Changed

- CI passou a rodar `composer lint` em vez de invocar `phpcs` diretamente,
  o que passa a cobrir `bin/ca` (antes ignorado silenciosamente).

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
