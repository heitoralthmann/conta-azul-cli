# Conta Azul CLI

[![Tests](https://github.com/heitoralthmann/conta-azul-cli/actions/workflows/tests.yml/badge.svg)](https://github.com/heitoralthmann/conta-azul-cli/actions/workflows/tests.yml)
[![Docs](https://github.com/heitoralthmann/conta-azul-cli/actions/workflows/docs.yml/badge.svg)](https://github.com/heitoralthmann/conta-azul-cli/actions/workflows/docs.yml)
![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-Apache%202.0-blue)

> **English:** unofficial PHP CLI wrapping the Conta Azul API (Financeiro, Pessoas, Produtos, Serviços, Contratos, Notas Fiscais, Vendas, Orçamentos, Captura) for consumption by AI agents. The Conta Azul API is Brazil-only, so the rest of this documentation is in Portuguese.

CLI em PHP/Symfony que expõe as famílias **Financeiro** (Finanças, Baixas, Cobranças), **Pessoas**, **Produtos**, **Serviços**, **Contratos**, **Notas Fiscais**, **Vendas**, **Orçamentos** e **Captura** da API Conta Azul para consumo por agentes de IA.

Cada invocação é de curta duração: faz uma chamada, escreve **TOON** em `stdout` e sai. Toda a complexidade de OAuth2 — fluxo inicial, persistência, refresh, rotação de token — fica encapsulada dentro do CLI.

O consumidor primário é um **agente**, não um humano. Por isso a saída padrão é [TOON](https://github.com/toon-format/toon) (mais compacto em tokens que JSON), o exit code é binário e os erros vêm em envelope estruturado com um campo `kind` estável. JSON compacto continua disponível com `--format=json`.

> **Projeto não oficial.** Este CLI não é mantido, endossado ou afiliado à Conta Azul. É um cliente de terceiros para a API pública da Conta Azul.

---

## 📚 Documentação

**A documentação completa está em [docs/](docs/index.md)** — e é publicada como site em
**<https://heitoralthmann.github.io/conta-azul-cli/>**.

| | |
|---|---|
| [Referência de comandos](docs/referencia/index.md) | Os 87 comandos, agrupados por endpoint, com cada parâmetro |
| [Instalação](docs/guia/instalacao.md) · [Configuração](docs/guia/configuracao.md) · [Autenticação](docs/guia/autenticacao.md) | Requisitos, variáveis de ambiente e o callback HTTPS que a Conta Azul exige |
| [Contrato de saída](docs/guia/contrato-de-saida.md) | Formatos, exit codes, envelope de erro e valores de `kind` |
| [Notas para quem for estender](docs/guia/estendendo.md) | As armadilhas confirmadas da API. Leitura obrigatória antes de mexer na integração |

Para agentes, a mesma documentação sai em forma de dados:
[`commands.json`](docs/commands.json), [`llms.txt`](docs/llms.txt) e
[`llms-full.txt`](docs/llms-full.txt).

---

## Requisitos

- PHP **8.4+** com as extensões `mbstring`, `openssl` e `ctype` (esta última já vem habilitada na maioria das builds)
- `posix` é **opcional** e só existe em Unix; toda chamada a ela é guardada, e o CLI roda sem ela — é assim que a suíte passa em `windows-latest` no CI
- Composer — só para o clone e para construir o PHAR; o PHAR pronto roda com PHP e mais nada
- Uma aplicação registrada no portal de desenvolvedores da Conta Azul (`client_id` + `client_secret`)
- Uma conta Conta Azul com **plano elegível para uso da API**

## Instalação

Três canais. O Homebrew é o caminho de uso em macOS e Linux; o PHAR é o mesmo
binário sem Homebrew por perto (e o caminho do Windows); o clone é o caminho de
desenvolvimento.

**Homebrew** — resolve o PHP 8.4+ como dependência e dá `brew upgrade`:

```bash
brew tap heitoralthmann/tap
brew install conta-azul-cli
```

Instala o mesmo `.phar` da release, sob dois nomes: `ca` e `conta-azul-cli`. Se
você já tem outro `ca` no `PATH` — inclusive de uma instalação manual anterior —
o `brew install` avisa que o nome está sombreado.

**Binário único (PHAR)** — um `ca` global, sem repositório por perto:

```bash
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar.sha256
shasum -a 256 -c conta-azul-cli.phar.sha256    # Linux: sha256sum -c
mkdir -p ~/.local/bin
install -m 0755 conta-azul-cli.phar ~/.local/bin/ca
```

`~/.local/bin` precisa estar no `PATH`. Para construir o mesmo artefato a partir do fonte, `composer build:phar` — e `composer smoke:phar` para conferi-lo antes de instalar.

**No Windows** o PHAR é invocado por `php conta-azul-cli.phar` (ou por um `ca.cmd` de uma linha), o checksum se confere com `Get-FileHash`, e o build local precisa de WSL ou Git Bash. A receita está em [Instalação](docs/guia/instalacao.md).

**Clone + Composer** — para mexer no código:

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
./bin/ca list
```

> Distribuição via `composer global require` está prevista, mas o pacote ainda não foi publicado no Packagist.

## Configuração

Numa instalação global, os comandos `ca config` funcionam **sem credencial nenhuma** — são justamente o caminho para criá-la:

```bash
ca config init                          # cria ~/.config/conta-azul-cli/.env
ca config set CA_CLIENT_ID <id>
ca config set CA_CLIENT_SECRET <secret>
ca config show                          # o secret sai como "(definido)", nunca em claro
```

Num clone, `cp .env.example .env` continua funcionando.

O `.env` e o `tokens.json` nascem `0600`, em diretório `0700` — em Unix. **No Windows o PHP não escreve bits de permissão**, e a proteção desses dois arquivos fica por conta das ACLs do perfil do usuário: a seção "Permissões dos arquivos" de [Configuração](docs/guia/configuracao.md) explica o que a garantia cobre e o que não cobre.

O arquivo de ambiente é procurado nesta ordem, e **o primeiro que existir vence, sem mesclagem**: `CA_CLI_ENV_FILE` → `<raiz do repo>/.env` → `~/.config/conta-azul-cli/.env`. `ca config path` responde qual está valendo e por quê. A precedência geral é: **flag de CLI → variável de ambiente → arquivo → default compilado**. Em produção, use variáveis de ambiente. `.env` e `tokens.json` **nunca** devem ser versionados.

O provedor da Conta Azul **recusa `redirect_uri` em `http://localhost`**: exige HTTPS e um domínio real. A receita completa — incluindo por que o domínio precisa ser `*.ddev.site` e por que `CA_AUTHORIZE_URL` e `CA_TOKEN_URL` andam em par — está em [Configuração](docs/guia/configuracao.md).

## Autenticação

```bash
ca auth login     # imprime uma URL, aguarda o redirect, grava os tokens
ca auth logout    # remove as credenciais locais
```

O refresh é automático e invisível. Para CI, veja [ambientes headless](docs/guia/autenticacao.md).

## Uso

Taxonomia: `ca <substantivo> <verbo> [args]`.

```bash
ca pessoa list --tamanho-pagina=10
ca conta-a-receber list --data-vencimento-de=2026-08-01 --data-vencimento-ate=2026-08-31
ca parcela get <id> --format=json | jq '.evento'
ca parcela baixar <id> --valor=100.50 --data=2026-08-27 --conta-financeira=<uuid>
ca protocolo get <id>
```

**Sucesso:** documento TOON em `stdout`, `stderr` vazio, exit code `0`.
**Erro:** um envelope estruturado em `stderr`, `stdout` vazio, exit code `1`.

```
correlation_id: a1b2…
http_status: 404
kind: client_error
message: …
protocol_id: null
retryable: false
```

O exit code é **binário** por design: o agente despacha sobre `kind`, não sobre o número. O enum de `kind` é [contrato estável](docs/guia/contrato-de-saida.md).

---

## Desenvolvimento

```bash
composer install
composer test             # PHPUnit
composer lint             # phpcs (inclui bin/ca)
composer format           # phpcbf
composer stan             # PHPStan (src level max, tests level 6)
composer docs:generate    # regenera a referência a partir do CLI + fragmentos
composer docs:check       # falha se a referência commitada divergiu
```

### Layout

```
src/
  Command/   comandos Symfony Console
  Api/       cliente HTTP da Conta Azul (retry, polling, paginação)
  Auth/      fluxo OAuth, token store, lock de refresh
  Output/    formatters (TOON/JSON), renderer, envelope de erro, redactor, logger
  Config/    resolução de env vars
  Error/     mapeamento de HTTP para o enum kind
tools/
  DocsGenerator/  gerador da referência de comandos
docs/
  _data/     fragmentos curados (prosa, endpoints, marca de verificação)
  referencia/ GERADO — não edite à mão
  guia/      guias escritos à mão
```

### A referência de comandos é gerada

`docs/referencia/`, `docs/commands.json` e `docs/llms*.txt` saem de
`composer docs:generate`, que lê as definições do Symfony Console e mescla os
fragmentos curados de `docs/_data/commands/`. O CI roda `composer docs:check`,
que falha quando uma nota cita um comando ou parâmetro que não existe mais,
quando um comando existente não está documentado, ou quando um grupo novo não
aparece no `nav` do `mkdocs.yml`.

Veja o site localmente com `pip install -r docs/requirements.txt && mkdocs serve`.

### Idioma

Superfície **humana** em pt-BR: README, `--help`, descrições, campo `message` do envelope. Superfície de **máquina** em inglês e estável: valores de `kind`, nomes de campo do envelope, variáveis de ambiente, identificadores e mensagens de commit.

### Limitações conhecidas

- `docs/financial-apis-openapi.yaml` ainda é um **placeholder**: a Conta Azul não publica uma URL estável para a spec OpenAPI, então não há automação de detecção de mudança na API — acompanhamento é manual (veja [CONTRIBUTING.md](CONTRIBUTING.md#acompanhando-mudanças-na-api-da-conta-azul)).

## Contribuição e segurança

Quer contribuir? Veja [CONTRIBUTING.md](CONTRIBUTING.md) e o [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Encontrou uma vulnerabilidade? Veja [SECURITY.md](SECURITY.md) — não abra uma issue pública.

## Licença

Apache 2.0. Veja [LICENSE](LICENSE).
