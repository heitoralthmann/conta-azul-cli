# Opções comuns

Aceitas por todos os comandos da Conta Azul:

| Opção | Efeito |
|---|---|
| `--format` | Formato da resposta: `toon` (padrão) ou `json`. `--format=toon` também é válido. |
| `--debug` | Grava log estruturado em `~/.cache/conta-azul-cli/log.jsonl`. Credenciais são redigidas. |
| `-q, --quiet` | Suprime tudo exceto erros. |
| `-h, --help` | Ajuda do comando. |
| `-V, --version` | Versão do CLI. |
| `-n, --no-interaction` | Não faz perguntas interativas. |

`list` e `help` ficam fora desse contrato: são os comandos nativos do Symfony,
usam texto por padrão e mantêm seus próprios `--format=txt|xml|json|md|rst` e
`--raw` (texto sem decoração). Eles não oferecem TOON.
