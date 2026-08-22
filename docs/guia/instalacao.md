# Instalação

## Requisitos

- PHP **8.4.1+** com as extensões `mbstring`, `openssl` e `ctype` (esta última já vem habilitada na maioria das builds)
- `posix` é **opcional**, e só existe em Unix. Toda chamada a ela é guardada por `function_exists`, e o CLI roda sem ela — é por isso que a suíte passa em `windows-latest` no CI, onde a extensão nem é instalada
- Composer — só para o caminho de clone e para construir o PHAR; o PHAR pronto roda com PHP e mais nada
- Pelo [Homebrew](#homebrew) nada disso é pré-requisito: o PHP entra como dependência da fórmula
- Uma aplicação registrada no portal de desenvolvedores da Conta Azul (`client_id` + `client_secret`)
- Uma conta Conta Azul com **plano elegível para uso da API** (veja [Solução de problemas](solucao-de-problemas.md))

## Quatro canais

| Canal | Para quê |
|---|---|
| **Homebrew** | Um `ca` global em macOS ou Linux, com `brew upgrade` para atualizar. É o caminho de uso. |
| **PHAR** | O mesmo binário, sem Homebrew por perto — e o caminho do Windows. |
| **Composer** | Para quem já vive no ecossistema PHP e quer o `ca` junto das outras ferramentas globais. |
| **Clone + Composer** | Mexer no código, rodar os testes, regenerar a documentação. É o caminho de desenvolvimento. |

Os quatro leem o mesmo arquivo de ambiente, encontrado pelo mesmo
[search path](configuracao.md). Os dois primeiros entregam o mesmo artefato: o
Homebrew instala exatamente o `.phar` que a release publica. A diferença prática
é que, dentro do PHAR, o `.env` da raiz do repositório não é candidato — as
credenciais precisam morar em `~/.config/conta-azul-cli/.env` (ou onde
`CA_CLI_ENV_FILE` apontar).

Os comandos das próximas seções assumem um shell Unix. Para Windows, vá direto
para [Windows](#windows).

## Homebrew

```bash
brew tap heitoralthmann/tap
brew trust --formula heitoralthmann/tap/conta-azul-cli
brew install conta-azul-cli
ca --version
```

Atualizar é `brew upgrade conta-azul-cli`; sair é `brew uninstall
conta-azul-cli`. Nenhum dos dois toca em `~/.config/conta-azul-cli/` — o CLI
resolve a configuração por `HOME`, não pelo prefixo do Homebrew, então
desinstalar não leva junto as suas credenciais.

### Por que o `brew trust`

A partir do **Homebrew 6**, fórmula de tap que não seja oficial não é carregada
sem consentimento explícito. Sem essa linha, o `install` — e depois o
`upgrade` — param com:

```
Error: Refusing to load formula heitoralthmann/tap/conta-azul-cli from
untrusted tap heitoralthmann/tap.
```

Em versões anteriores do Homebrew o comando `brew trust` não existe, e tapar já
bastava; se o seu `brew` reclamar que o comando é desconhecido, é só pular a
linha.

Note que a receita acima confia **na fórmula**, não no tap inteiro. A diferença
importa: `brew trust heitoralthmann/tap` passaria a aceitar qualquer fórmula
que eu venha a adicionar ali depois, sem você olhar. O próprio Homebrew
recomenda o recorte mais estreito, e é ele que está documentado aqui.

### O que o tap instala

**PHP 8.4.1+ vira dependência declarada, não pré-requisito documentado.** A
fórmula tem `depends_on "php"`: se você não tem o PHP do Homebrew, ele é
instalado junto — é um download grande na primeira vez. O executável instalado é
um wrapper de uma linha que chama esse PHP, em vez de depender do shebang
`#!/usr/bin/env php` do artefato, que pegaria o primeiro `php` do `PATH` — que
pode ser anterior ao 8.4 exigido, ou não existir.

**Dois nomes, o mesmo wrapper:** `ca` e `conta-azul-cli`. `ca` é curto e
genérico, e pode já estar ocupado na sua máquina — inclusive por uma instalação
manual anterior deste mesmo CLI em `~/.local/bin/ca`. Quando esse for o caso, o
próprio `brew install` avisa:

```
The following conta-azul-cli executables are shadowed by other commands
earlier in your PATH:
  ca (shadowed by /Users/você/.local/bin/ca)
```

Vindo do caminho manual, `rm ~/.local/bin/ca` resolve — o Homebrew passa a
responder pelo nome. Se o `ca` que atrapalha for outro programa, use
`conta-azul-cli`.

### Integridade

O `sha256` que o `brew` confere antes de instalar está versionado em
[`heitoralthmann/homebrew-tap`](https://github.com/heitoralthmann/homebrew-tap),
com histórico de git — um canal diferente daquele por onde o binário viaja. É
melhor que o `.sha256` ao lado do `.phar` da seção seguinte, porque não viaja
junto com o que ele descreve. A fórmula é reescrita automaticamente pelo
workflow de release a cada tag.

Para provar também a **origem** do `.phar` que o `brew` baixou, veja
[Proveniência](#proveniencia).

## PHAR

### Baixar da release

```bash
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar
curl -LO https://github.com/heitoralthmann/conta-azul-cli/releases/latest/download/conta-azul-cli.phar.sha256
shasum -a 256 -c conta-azul-cli.phar.sha256   # Linux: sha256sum -c
```

O `.sha256` é gerado pelo mesmo workflow que publica o `.phar` e viaja pelo
mesmo canal: sozinho, ele prova que o download não veio corrompido, não de onde
o arquivo veio. Quem quiser a segunda garantia, use a atestação de proveniência
descrita [abaixo](#proveniencia).

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

## Proveniência

O workflow de release atesta a proveniência de cada `.phar` que publica: uma
declaração assinada, ligando o digest daquele arquivo ao workflow, ao
repositório e ao commit que o construíram. Para conferir:

```bash
gh attestation verify conta-azul-cli.phar --repo heitoralthmann/conta-azul-cli
```

Funciona com o arquivo baixado da release e também com o que o Homebrew
instalou:

```bash
gh attestation verify "$(brew --prefix conta-azul-cli)/libexec/conta-azul-cli.phar" \
  --repo heitoralthmann/conta-azul-cli
```

Precisa do [GitHub CLI](https://cli.github.com/) e de rede — a verificação
consulta o registro público de atestações do GitHub.

Artefatos publicados antes de o passo existir no workflow não têm atestação
nenhuma, e para eles o comando responde que não encontrou nada. Isso não diz que
o arquivo é falso; diz que ele é anterior a esta garantia.

### O que isso prova, e o que não prova

**Prova** que o binário na sua mão saiu do workflow de release deste
repositório, a partir de um commit específico — não de uma release forjada, nem
de um arquivo trocado no caminho. É uma garantia diferente da do `.sha256`, que
diz apenas que o download não corrompeu.

**Não prova** que eu revisei ou aprovei aquela versão. A atestação é emitida
pela identidade do workflow, não pela minha: quem conseguisse alterar o
workflow e disparar uma release produziria uma atestação igualmente válida. O
que ela fecha é o caminho entre o build e o seu disco, não o que aconteceu antes
do build.

Não há assinatura GPG minha sobre o artefato.

## Composer

O pacote está no Packagist como
[`heitoralthmann/conta-azul-cli`](https://packagist.org/packages/heitoralthmann/conta-azul-cli):

```bash
composer global require heitoralthmann/conta-azul-cli
ca --version
```

O `ca` vai parar em `$COMPOSER_HOME/vendor/bin`, que precisa estar no `PATH`
(`composer global config bin-dir --absolute` responde qual é o caminho na sua
máquina). Atualizar é `composer global update heitoralthmann/conta-azul-cli`.

Uma ressalva que os outros canais não têm: `composer global` instala num espaço
de dependências **compartilhado com as suas outras ferramentas globais**, então
duas ferramentas que exijam versões incompatíveis do mesmo pacote entram em
conflito. O PHAR e o Homebrew não sofrem disso — dentro do PHAR as dependências
vêm empacotadas e isoladas. Se o `ca` for a única coisa que você quer, prefira
um daqueles dois.

## Primeira configuração

Vale igual para o Homebrew, o PHAR e o Composer. Recém-instalado e longe de
qualquer repositório, o CLI ainda não tem credenciais. Os comandos `ca config` funcionam nesse estado justamente para
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

## Windows

**O código é exercitado no Windows a cada push.** A matriz do CI roda
`windows-latest` em PHP 8.4 e 8.5, e a suíte inteira passa nas duas. `posix`
não entra nessa matriz de propósito — a extensão não existe naquela plataforma,
e o CLI guarda toda chamada a ela.

O nível de suporte, porém, merece ser dito com precisão: o que é exercitado é o
**código**. O ferramental de build e esta própria seção são novos e muito menos
rodados. Se algo aqui divergir da realidade, é aqui que está o erro, não no CLI.

O tap do Homebrew não atende o Windows: aqui o caminho é o PHAR, com o `ca.cmd`
descrito abaixo.

### Rodar o PHAR

O PHAR não tem shebang que o Windows entenda, então quem o invoca é o PHP:

```powershell
php conta-azul-cli.phar --version
```

Para um `ca` seco, crie um `ca.cmd` **ao lado** do `.phar`:

```bat
@php "%~dp0conta-azul-cli.phar" %*
```

`%~dp0` é o diretório do próprio `.cmd`, então o par (`ca.cmd` +
`conta-azul-cli.phar`) pode morar em qualquer lugar, desde que os dois fiquem
juntos. `%APPDATA%\conta-azul-cli\bin` é um destino razoável. Acrescente esse
diretório ao `PATH` do usuário:

```powershell
$bin  = "$env:APPDATA\conta-azul-cli\bin"
$path = [Environment]::GetEnvironmentVariable('Path', 'User')
[Environment]::SetEnvironmentVariable('Path', "$path;$bin", 'User')
```

Repare na leitura do escopo `User` antes da escrita: concatenar `$env:Path`, que
é o `PATH` já resolvido do processo, copiaria as entradas da máquina inteira
para dentro do seu perfil. Abra um terminal novo e `ca --version` responde.

### Conferir o checksum

`shasum` e `sha256sum` não existem no Windows; o PowerShell traz `Get-FileHash`:

```powershell
$publicado = (Get-Content conta-azul-cli.phar.sha256).Split(' ')[0]
$local     = (Get-FileHash conta-azul-cli.phar -Algorithm SHA256).Hash.ToLower()
if ($local -eq $publicado) { 'ok' } else { 'DIVERGE' }
```

O `.sha256` publicado vem no formato do `sha256sum` — `<hash>  <arquivo>` —,
daí o `Split`. Vale aqui a mesma ressalva do caminho Unix: o checksum viaja pelo
mesmo canal que o binário, então sozinho ele prova integridade do download, não
origem. A [atestação de proveniência](#proveniencia) responde essa outra
pergunta, e o `gh attestation verify` funciona igual no PowerShell.

### Construir do fonte precisa de bash

`composer build:phar` e `composer smoke:phar` chamam `tools/install-box.sh` e
`tools/smoke-test.sh`, que são scripts bash. Eles rodam sob **WSL** ou **Git
Bash**; no `cmd.exe` ou no PowerShell puro, não. No Windows, baixar o artefato
da release é o caminho de menor resistência.

### Onde a configuração fica

O CLI resolve o diretório do usuário por `USERPROFILE` (ou `HOMEDRIVE` +
`HOMEPATH`), então o `~/.config/conta-azul-cli/` que esta documentação cita
vira:

```
C:\Users\<você>\.config\conta-azul-cli\.env
C:\Users\<você>\.config\conta-azul-cli\tokens.json
```

Funciona — mas **não é a convenção `%APPDATA%`** que um usuário de Windows
espera encontrar, e vale dizer isso em voz alta para ninguém tratar como bug. É
o mesmo caminho relativo em todas as plataformas, o que deixa uma única regra
para documentar e depurar.

Uma armadilha real: `HOME` é consultada **antes** de `USERPROFILE`, e shells
como o Git Bash e o WSL definem `HOME` por conta própria. O mesmo CLI invocado
do PowerShell e de um Git Bash pode, portanto, resolver arquivos diferentes.
Quando a dúvida aparecer, `ca config path` responde qual está valendo — em
qualquer shell.

### Permissões

O `0600` que o CLI aplica ao `.env` e ao `tokens.json` **não vale no Windows**:
o `chmod` do PHP ali só liga e desliga o atributo de somente-leitura. Os dois
arquivos guardam credencial — client secret e refresh token —, então leia
[Permissões dos arquivos](configuracao.md#permissoes) antes de usar o CLI numa
máquina compartilhada.

### Clone

O caminho de desenvolvimento funciona igual:

```powershell
git clone https://github.com/heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
copy .env.example .env
php bin\ca list
```

Invoque pelo `php`: `bin/ca` não tem extensão, e o Windows decide o que é
executável pela lista `PATHEXT`. `composer test`, `composer lint` e
`composer stan` rodam normalmente — são os mesmos comandos que o CI executa em
`windows-latest`.
