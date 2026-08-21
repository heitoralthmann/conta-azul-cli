# Instalação

## Requisitos

- PHP **8.4+** com as extensões `mbstring`, `openssl` e `posix`
- Composer — só para o caminho de clone e para construir o PHAR; o PHAR pronto roda com PHP e mais nada
- Uma aplicação registrada no portal de desenvolvedores da Conta Azul (`client_id` + `client_secret`)
- Uma conta Conta Azul com **plano elegível para uso da API** (veja [Solução de problemas](solucao-de-problemas.md))

## Dois canais

| Canal | Para quê |
|---|---|
| **PHAR** | Ter um `ca` global na máquina, longe do diretório do projeto. É o caminho de uso. |
| **Clone + Composer** | Mexer no código, rodar os testes, regenerar a documentação. É o caminho de desenvolvimento. |

Os dois leem o mesmo arquivo de ambiente, encontrado pelo mesmo
[search path](configuracao.md). A diferença prática é que, dentro do PHAR, o
`.env` da raiz do repositório não é candidato — as credenciais precisam morar
em `~/.config/conta-azul-cli/.env` (ou onde `CA_CLI_ENV_FILE` apontar).

## PHAR

### Baixar da release

```bash
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar.sha256
shasum -a 256 -c conta-azul-cli.phar.sha256   # Linux: sha256sum -c
```

O `.sha256` é gerado pelo mesmo workflow que publica o `.phar` e viaja pelo
mesmo canal: ele prova que o download não veio corrompido, não que o artefato é
autêntico. Não há assinatura.

Depois:

```bash
mkdir -p ~/.local/bin
install -m 0755 conta-azul-cli.phar ~/.local/bin/ca
ca --version
```

`install` não cria o diretório de destino — daí o `mkdir -p`.

### Construir localmente

```bash
composer build:phar
mkdir -p ~/.local/bin
install -m 0755 build/conta-azul-cli.phar ~/.local/bin/ca
```

O script baixa o [Box](https://github.com/box-project/box) de uma release fixa
conferindo o SHA-256, troca o `vendor/` para `--no-dev`, compila e **restaura as
dependências de desenvolvimento ao final** — inclusive se o build falhar no
meio. Para conferir o artefato antes de instalar:

```bash
composer smoke:phar
```

São seis verificações contra o `.phar` construído, as mesmas que o CI roda antes
de anexar o arquivo a uma release.

### `~/.local/bin` no `PATH`

`install` não mexe no `PATH`. Se `ca --version` responder "command not found",
o diretório não está na busca do shell:

```bash
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc   # ou ~/.bashrc
```

Qualquer outro diretório já no `PATH` serve igual — `~/.local/bin` é só a
convenção mais comum para binário de usuário.

### Primeira configuração

Recém-instalado e longe de qualquer repositório, o CLI ainda não tem
credenciais. Os comandos `ca config` funcionam nesse estado justamente para
resolvê-lo:

```bash
ca config init                              # cria ~/.config/conta-azul-cli/.env
ca config set CA_CLIENT_ID <id>
ca config set CA_CLIENT_SECRET <secret>
ca config show                              # confere; o secret sai como "(definido)"
ca auth login
```

O `ca auth login` só completa depois de o `redirect_uri` estar registrado no
painel do app — e o provedor da Conta Azul **recusa `http://localhost`**, o
default compilado. A receita com `mkcert`, e o detalhe de cada variável, estão
em [Configuração](configuracao.md).

## Clone + Composer

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
cp .env.example .env    # preencha CA_CLIENT_ID e CA_CLIENT_SECRET
./bin/ca list
```

Num clone, o `.env` da raiz do repositório vence o arquivo do usuário — o
comportamento de sempre. `ca config path` responde qual arquivo está valendo em
qualquer situação.

> Distribuição via `composer global require` está prevista, mas o pacote ainda não foi publicado no Packagist.
