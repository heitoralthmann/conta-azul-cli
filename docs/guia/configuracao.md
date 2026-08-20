# Configuração

Copie `.env.example` para `.env` e preencha as credenciais:

```bash
cp .env.example .env
```

A precedência de configuração é: **flag de CLI → variável de ambiente → arquivo `.env` → default compilado**. O `.env` é procurado na raiz do projeto (ao lado de `bin/`), independentemente do diretório de onde você invoca o CLI, e variáveis já presentes no ambiente têm precedência sobre ele. Em produção, use variáveis de ambiente.

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

`.env` e `tokens.json` **nunca** devem ser versionados.

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
