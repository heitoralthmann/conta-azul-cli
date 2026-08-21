# Configuração

A precedência de configuração é: **flag de CLI → variável de ambiente → arquivo `.env` → default compilado**. Uma variável já exportada no ambiente ganha do arquivo, sempre. Em produção, use variáveis de ambiente.

## Onde o arquivo é procurado

O CLI lê **um** arquivo de ambiente. O primeiro candidato que existir vence, e **nada é mesclado**:

| Ordem | Candidato | Observação |
|---|---|---|
| 1 | o arquivo indicado por `CA_CLI_ENV_FILE` | Escape hatch explícito, por invocação. Se a variável estiver definida e o arquivo não puder ser lido, o CLI **falha** — nunca cai em silêncio para o próximo candidato. |
| 2 | `<raiz do repo>/.env` | O caminho de sempre, ao lado de `bin/`, independentemente do diretório de onde você invoca o CLI. **Pulado dentro de um PHAR**: ali ele resolveria para um `phar://…/.env` que ninguém pode criar. |
| 3 | `~/.config/conta-azul-cli/.env` | O arquivo do usuário, no mesmo diretório em que já moram `tokens.json` e o log. É o que torna um `ca` global utilizável. |

Mesclar candidatos tornaria `ca config set` ambíguo — gravou em qual arquivo? — e transformaria um arquivo esquecido num override parcial silencioso. A regra cabe numa frase de propósito, e `ca config path` imprime a decisão inteira, com o motivo de cada candidato ter vencido ou sido descartado:

```bash
ca config path
```

> **`getcwd()/.env` não é candidato, e isso é deliberado.** Se o diretório de trabalho entrasse na busca, rodar o `ca` de dentro de qualquer projeto alheio que tenha um `.env` faria o CLI absorver as variáveis daquele projeto — credenciais de terceiros, em silêncio, sem nada na saída indicando de onde vieram. `CA_CLI_ENV_FILE` cobre a mesma necessidade de forma explícita, por invocação, e aparece em `ca config path`.

## Configurando uma instalação global

Com o `ca` instalado como binário ([Instalação](instalacao.md)), não há repositório por perto e o candidato 3 é o que vale. Os comandos `ca config` funcionam **mesmo sem credencial nenhuma** — são o caminho para sair desse estado:

```bash
ca config init                        # cria ~/.config/conta-azul-cli/.env
ca config set CA_CLIENT_ID <id>
ca config set CA_CLIENT_SECRET <secret>
ca config show                        # confere o resultado
```

O arquivo nasce `0600`, dentro de um diretório `0700` — em Unix. No Windows, essa garantia não existe; veja [Permissões dos arquivos](#permissoes).

`ca config set` valida a chave contra a lista de variáveis conhecidas: um nome digitado errado é recusado na hora, em vez de virar uma linha no arquivo que nunca faz efeito.

**`init` e `set` não têm o mesmo alvo.** `ca config init` grava **sempre** em `~/.config/conta-azul-cli/.env`, mesmo que outro candidato esteja valendo — é a razão de ele existir. Já `ca config set` grava no arquivo **em vigor**, seja ele qual for. Num clone que tenha `.env` na raiz, `init` cria um segundo arquivo que só passa a valer quando o primeiro sair do caminho; `ca config path` mostra isso.

`ca config show` lista todas as variáveis com o valor efetivo e a origem de cada uma — `ambiente`, `arquivo` ou `default`. **A saída nunca contém segredo:** `CA_CLIENT_SECRET` e `CA_BOOTSTRAP_REFRESH_TOKEN` aparecem só como `(definido)` ou `(ausente)`, sem máscara parcial e sem opção para revelar. É o que torna essa saída segura para colar numa issue.

Os quatro comandos, com o que cada um responde: [Configuração na referência](../referencia/config.md).

## Configurando um clone

Num clone, o candidato 2 vence. Copie `.env.example` para `.env` e preencha as credenciais:

```bash
cp .env.example .env
```

## Variáveis

| Variável | Obrigatória | Default |
|---|---|---|
| `CA_CLIENT_ID` | sim | — |
| `CA_CLIENT_SECRET` | sim | — |
| `CA_REDIRECT_URI` | não | `http://localhost:9876/callback` |
| `CA_SCOPE` | não | omitido da requisição |
| `CA_CALLBACK_CERT` | não | — |
| `CA_CALLBACK_KEY` | não | — |
| `CA_API_BASE_URL` | não | `https://api-v2.contaazul.com` |
| `CA_AUTH_BASE_URL` | não | `https://auth.contaazul.com` |
| `CA_AUTHORIZE_URL` | não | `{CA_AUTH_BASE_URL}/oauth2/authorize` |
| `CA_TOKEN_URL` | não | `{CA_AUTH_BASE_URL}/oauth2/token` |
| `CA_CALLBACK_TIMEOUT` | não | `300` (segundos) |
| `CA_CLI_TOKEN_PATH` | não | `~/.config/conta-azul-cli/tokens.json` |
| `CA_BOOTSTRAP_REFRESH_TOKEN` | não | — |
| `CA_CLI_ENV_FILE` | não | — (veja abaixo) |

`CA_CLI_ENV_FILE` é a única da lista que **só funciona no ambiente**, nunca dentro de um arquivo: ela *escolhe* o arquivo, então defini-la lá dentro jamais poderia fazer efeito. Por isso `ca config set` a recusa, e é `ca config path` que mostra seu valor.

`.env` e `tokens.json` **nunca** devem ser versionados.

## Permissões dos arquivos { #permissoes }

O CLI grava dois arquivos que carregam credencial:

| Arquivo | O que guarda |
|---|---|
| `~/.config/conta-azul-cli/.env` | `CA_CLIENT_SECRET` — e o `CA_BOOTSTRAP_REFRESH_TOKEN`, se você o definir ali |
| `~/.config/conta-azul-cli/tokens.json` | O access token e o **refresh token** do OAuth, a credencial de longa duração |

Nos dois casos o CLI cria o diretório com `0700` e o arquivo com `0600`, e **reaplica a permissão a cada escrita**, não só na criação. Em Unix isso significa o que promete: nenhum outro usuário da máquina lê esses arquivos.

> **No Windows essa garantia não existe.** O `chmod` do PHP naquela plataforma só liga e desliga o atributo de somente-leitura — ele não escreve bits de modo POSIX, porque o sistema de arquivos não os tem. O código chama `chmod` em todas as plataformas e está correto; o que não existe no Windows é o efeito. Quem protege os arquivos ali são as ACLs do próprio perfil do usuário (`C:\Users\<você>`), que por padrão já barram os demais usuários locais.
>
> Numa máquina Windows de um usuário só, isso costuma ser suficiente. O que **não** é verdade é a garantia: num perfil com ACL afrouxada, numa pasta sincronizada para a nuvem ou num diretório compartilhado, não há um `0600` segurando a ponta. Se for o seu caso, restrinja o diretório à mão (`icacls`) ou mantenha as credenciais em variáveis de ambiente, fora de arquivo.
>
> A suíte de testes se comporta do mesmo jeito: em Windows, os casos que verificam bits de permissão são pulados explicitamente, em vez de afirmar algo que a plataforma não sustenta.

Uma ressalva sobre o caminho de clone: `cp .env.example .env` cria o arquivo com o que o seu `umask` mandar — tipicamente `0644`, legível por qualquer usuário da máquina. Só `ca config init` e `ca config set` aplicam `0600`. Num clone em máquina compartilhada, vale um `chmod 600 .env` depois de copiar.

## Sobre `CA_SCOPE`

Valores aceitos dependem do app registrado. O scope do app de produção é:

```bash
CA_SCOPE="openid profile aws.cognito.signin.user.admin"
```

O valor **precisa de aspas**: contém espaços, e o Dotenv rejeita valores não citados com espaço.

Nem todo scope serve: `financeiro` é recusado com `invalid_scope`. Se `CA_SCOPE` ficar indefinida, o parâmetro é omitido da requisição e o provedor aplica os escopos configurados no painel do app — também um caminho válido. Ao receber `invalid_scope` no login, o primeiro lugar a olhar é o valor no `.env`.

## `CA_AUTHORIZE_URL` e `CA_TOKEN_URL` andam em par

**Um authorization code só pode ser resgatado no servidor que o emitiu.** Os dois endpoints têm que pertencer ao mesmo servidor de autorização. Os defaults já satisfazem isso e servem tanto produção quanto sandbox:

```
CA_AUTHORIZE_URL = https://auth.contaazul.com/oauth2/authorize
CA_TOKEN_URL     = https://auth.contaazul.com/oauth2/token
```

Na prática, só mexa nessas variáveis se a Conta Azul mudar os endpoints — e mexa nas duas.

> **Não aponte `CA_AUTHORIZE_URL` para `https://login.contaazul.com/#/oauth/authorize`.** Aquela tela funciona, exibe o nome do app e devolve um `code` no callback — mas é a SPA de login, e ela emite o code através de `api-v2.contaazul.com/oauth/authorize`, um emissor diferente. O `/oauth2/token` do Cognito não reconhece esse code, e a troca falha com **`invalid_grant`** logo depois de um login aparentemente bem-sucedido. Já caímos nessa: o sintoma não aponta para a causa, porque a parte visível do fluxo se comporta como se estivesse certa.

## Callback OAuth com HTTPS

O provedor da Conta Azul **recusa `redirect_uri` em `http://localhost`**: exige HTTPS e um domínio real. A saída é usar `mkcert` com um domínio que já resolve para `127.0.0.1` via DNS público — `*.ddev.site` — sem mexer em `/etc/hosts`.

> **Não altere esse domínio.** O nome sugere uma dependência de DDEV que **não existe**: o projeto não usa DDEV, e `*.ddev.site` é apenas um wildcard DNS público apontando para `127.0.0.1`. A escolha é imposta pelo provedor — já tentamos trocar por um nome mais neutro e não funcionou. Ao registrar a aplicação com `conta-azul-cli.localtest.me`, que tem exatamente a mesma propriedade de DNS, o portal da Conta Azul respondeu **erro interno de servidor** e recusou o cadastro; com `ddev.site` aceitou. O critério de validação de domínio deles não é documentado, então vale o valor que funciona.

```bash
brew install mkcert
mkcert -install
mkdir -p .certs
mkcert -cert-file .certs/cert.pem -key-file .certs/key.pem conta-azul-cli.ddev.site
```

E no `.env`:

```bash
CA_REDIRECT_URI=https://conta-azul-cli.ddev.site:9876/callback
CA_CALLBACK_CERT=/caminho/absoluto/.certs/cert.pem
CA_CALLBACK_KEY=/caminho/absoluto/.certs/key.pem
```

Registre exatamente esse `redirect_uri` no painel do app na Conta Azul. Quando `CA_CALLBACK_CERT` e `CA_CALLBACK_KEY` estão presentes, o servidor de callback abre um socket TLS; sem elas, ele cai no modo `http://` simples.
