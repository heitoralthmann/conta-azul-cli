# Conta Azul CLI

CLI em PHP/Symfony que expõe as famílias **Financeiro** (Finanças, Baixas, Cobranças), **Pessoas**, **Produtos** e **Serviços** da API Conta Azul para consumo por agentes de IA.

Cada invocação é de curta duração: faz uma chamada, escreve JSON compacto em `stdout` e sai. Toda a complexidade de OAuth2 — fluxo inicial, persistência, refresh, rotação de token — fica encapsulada dentro do CLI.

O consumidor primário é um **agente**, não um humano. Por isso a saída é JSON compacto, o exit code é binário e os erros vêm em envelope estruturado com um campo `kind` estável.

> **Projeto não oficial.** Este CLI não é mantido, endossado ou afiliado à Conta Azul. É um cliente de terceiros para a API pública da Conta Azul.

---

## Requisitos

- PHP **8.3+** com as extensões `mbstring`, `openssl` e `posix`
- Composer
- Uma aplicação registrada no portal de desenvolvedores da Conta Azul (`client_id` + `client_secret`)
- Uma conta Conta Azul com **plano elegível para uso da API** (veja [Solução de problemas](#solução-de-problemas))

## Instalação

### A partir do repositório

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
./bin/ca list
```

### PHAR

Baixe o `conta-azul-cli.phar` da página de releases do GitHub:

```bash
chmod +x conta-azul-cli.phar
mv conta-azul-cli.phar /usr/local/bin/ca
```

> Distribuição via `composer global require` está prevista, mas o pacote ainda não foi publicado no Packagist.

---

## Configuração

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

### Sobre `CA_SCOPE`

Valores aceitos dependem do app registrado. O scope do app de produção é:

```bash
CA_SCOPE="openid profile aws.cognito.signin.user.admin"
```

O valor **precisa de aspas**: contém espaços, e o Dotenv rejeita valores não citados com espaço.

Nem todo scope serve: `financeiro` é recusado com `invalid_scope`. Se `CA_SCOPE` ficar indefinida, o parâmetro é omitido da requisição e o provedor aplica os escopos configurados no painel do app — também um caminho válido. Ao receber `invalid_scope` no login, o primeiro lugar a olhar é o valor no `.env`.

### `CA_AUTHORIZE_URL` e `CA_TOKEN_URL` andam em par

**Um authorization code só pode ser resgatado no servidor que o emitiu.** Os dois endpoints têm que pertencer ao mesmo servidor de autorização. Os defaults já satisfazem isso e servem tanto produção quanto sandbox:

```
CA_AUTHORIZE_URL = https://auth.contaazul.com/oauth2/authorize
CA_TOKEN_URL     = https://auth.contaazul.com/oauth2/token
```

Na prática, só mexa nessas variáveis se a Conta Azul mudar os endpoints — e mexa nas duas.

> **Não aponte `CA_AUTHORIZE_URL` para `https://login.contaazul.com/#/oauth/authorize`.** Aquela tela funciona, exibe o nome do app e devolve um `code` no callback — mas é a SPA de login, e ela emite o code através de `api-v2.contaazul.com/oauth/authorize`, um emissor diferente. O `/oauth2/token` do Cognito não reconhece esse code, e a troca falha com **`invalid_grant`** logo depois de um login aparentemente bem-sucedido. Já caímos nessa: o sintoma não aponta para a causa, porque a parte visível do fluxo se comporta como se estivesse certa.

### Callback OAuth com HTTPS

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

---

## Autenticação

```bash
ca auth login
```

O comando imprime uma URL, sobe um listener local na porta **9876** e aguarda o redirect. Abra a URL no navegador, complete o login, e os tokens são gravados em `~/.config/conta-azul-cli/tokens.json` com permissão `0600`.

```bash
ca auth logout    # remove as credenciais locais
```

**Refresh é automático e invisível.** O access token é renovado preventivamente quando restam menos de 60 s de validade, e reativamente uma única vez em caso de `401`. Refresh tokens rotacionam a cada uso e o novo valor é sempre persistido. Invocações concorrentes coordenam via `flock()` sobre o arquivo de tokens, de modo que apenas uma delas faz o refresh e as demais leem o resultado.

### Ambientes headless (CI)

Rode `ca auth login` uma vez numa máquina com navegador, copie o `refresh_token` do `tokens.json` e exponha no CI:

```bash
export CA_BOOTSTRAP_REFRESH_TOKEN=<token>
```

Na primeira invocação o CLI detecta a variável, faz o refresh, persiste o resultado em arquivo e segue. Depois desse primeiro arranque a variável pode ser removida.

---

## Comandos

> **A referência completa é [`COMMANDS.md`](COMMANDS.md)** — todos os comandos agrupados por endpoint, com cada parâmetro, os formatos de data, os valores de `kind` e o que a API não oferece. É o arquivo a consultar (e a atualizar) ao mexer na integração. O resumo abaixo existe só para dar o panorama.

Taxonomia: `ca <substantivo> <verbo> [args]`. Substantivos em português espelham a API; verbos seguem o idioma do Symfony Console, exceto quando o verbo é conceito de domínio (`baixar`).

### Consultas

```bash
ca conta-a-receber list [--data-vencimento-de=YYYY-MM-DD] [--data-vencimento-ate=YYYY-MM-DD] [--pagina=1] [--tamanho-pagina=50]
ca conta-a-pagar list   [--data-vencimento-de=YYYY-MM-DD] [--data-vencimento-ate=YYYY-MM-DD] [--pagina=1] [--tamanho-pagina=50]
ca parcela get <id>
ca conta-financeira list [--pagina=1] [--tamanho-pagina=50]
ca conta-financeira saldo --id=<id>
ca categoria list [--pagina=1] [--tamanho-pagina=50]
ca centro-de-custo list [--pagina=1] [--tamanho-pagina=50]
ca financeiro alteracoes [--data-inicio=<ISO8601>] [--data-fim=<ISO8601>]
ca protocolo get <id>
```

### Escritas

```bash
ca conta-a-receber create --json='{...}' [--poll-timeout=60] [--no-wait]
ca conta-a-pagar create   --json='{...}' [--poll-timeout=60] [--no-wait]
ca parcela baixar <id> --valor=100.50 --data=2026-05-27 [--poll-timeout=60] [--no-wait]
```

### Intervalos de data obrigatórios

As buscas de contas e o feed de alterações **exigem** intervalo de datas — sem ele a API responde 400. Quando as opções não são informadas, o CLI assume o **mês corrente** e avisa em stderr qual recorte aplicou:

```json
{"kind":"warning","message":"Intervalo de vencimento não informado por completo; usando 2026-08-01 a 2026-08-31. …"}
```

O stdout continua contendo só o payload, então o aviso não atrapalha `| jq`. Informar as duas opções silencia o aviso.

Em `financeiro alteracoes` as datas vão em ISO 8601 **sem timezone** (`2026-08-01T00:00:00`); com sufixo `Z` ou offset a API responde 400.

### Recursos sem comando

A API v1 não expõe operação equivalente para **lançamentos**, **cobranças**, busca por id de conta a pagar/receber, update/delete desses eventos, nem criação de transferência. Os comandos correspondentes existiam apontando para paths inexistentes e foram removidos em vez de continuarem anunciando o que não funciona. Dois endpoints reais ficaram sem comando por ora: `GET /v1/financeiro/transferencias` e `GET /v1/financeiro/eventos-financeiros/{id_evento}/parcelas`.

### Paginação

`--tamanho-pagina` aceita apenas `10`, `20`, `50`, `100`, `200`, `500` ou `1000`. Qualquer outro valor falha como `client_error` **antes** de a requisição sair do CLI.

Não há auto-paginação nem flag `--all` — o agente pagina explicitamente, e o CLI não esconde latência ou consumo de quota dentro de loops invisíveis.

---

## Contrato de saída

**Sucesso:** JSON compacto em `stdout`, `stderr` vazio, exit code `0`.

**Erro:** um único objeto JSON compacto em `stderr`, `stdout` vazio, exit code `1`.

```json
{"kind":"client_error","retryable":false,"http_status":403,"protocol_id":null,"correlation_id":"...","message":"..."}
```

O exit code é **binário** por design: o agente despacha sobre `kind`, não sobre o número.

| `kind` | Significado | `retryable` |
|---|---|---|
| `client_error` | 4xx exceto 429. Corrigir a requisição antes de retentar. | `false` |
| `transient` | Erro de transporte antes de chegar à API. Seguro re-executar. | `true` |
| `ambiguous` | Resposta perdida após o envio. A escrita **pode** ter sido aplicada — reconcilie via `financeiro alteracoes`. | `false` |
| `auth_failed` | Refresh falhou ou credenciais inválidas. Rode `ca auth login`. | `false` |
| `rate_limited` | 429 da API. Aguarde `Retry-After` ou aplique backoff. | `true` |
| `poll_timeout_known_id` | Polling estourou o teto sem status terminal. Consulte `ca protocolo get <protocol_id>`. | `false` |
| `poll_drop_known_id` | Polling interrompido; `protocol_id` conhecido. Retome o polling. | `true` |
| `server_error` | 5xx em GET após esgotar retries. Em escritas, 5xx vira `ambiguous`. | depende |

O enum é **contrato estável**: remover ou renomear um valor exige bump major. Adicionar valores novos é minor — o agente deve tratar `kind` desconhecido como erro genérico.

---

## Escritas assíncronas

Quando a API responde `202 + protocolId`, o CLI faz **polling interno** (backoff de 1 s dobrando até 8 s, teto total de 60 s) até o status virar terminal, e só então retorna. Uma invocação equivale a sucesso atômico ou falha atômica.

- `--poll-timeout=<segundos>` ajusta o teto
- `--no-wait` retorna o `202` cru imediatamente, sem polling

Com `--no-wait`, cabe ao agente consultar `ca protocolo get <id>` depois.

### Idempotência

O CLI **não** deduplica escritas, **não** retorna respostas em cache e **não** retenta `POST` em erro de transporte. Dedupe automático sobre hash de body seria um footgun em contexto financeiro — dois pagamentos legítimos idênticos seriam silenciosamente colapsados em um.

A contrapartida: em `kind: "ambiguous"`, **a reconciliação é responsabilidade do agente**, comparando entidade + valor + data via `ca financeiro alteracoes --data-inicio=<...> --data-fim=<...>`.

### Retries

- **GET:** retry em erro de transporte, 429, 502, 503 e 504 — backoff de 0,5 s / 2 s / 8 s com jitter de ±20 %, máximo de 3 tentativas. Honra `Retry-After`.
- **POST/PUT/PATCH/DELETE:** retry **apenas** em 429, que garante que a requisição não foi processada.

### Limites de taxa

A Conta Azul limita **10 req/s** e **600 req/min** por tenant. O CLI não implementa throttle client-side: cabe ao agente não disparar mais de 10 invocações concorrentes contra o mesmo tenant. Excedentes recebem 429 e são retentadas conforme a política acima.

---

## Observabilidade

Por padrão o CLI é **silencioso**. Com `--verbose` ou `--debug`, ele grava log estruturado JSONL em `~/.cache/conta-azul-cli/log.jsonl` (rotação em 10 MB, 3 arquivos históricos).

O log **nunca** vai para `stderr` — esse canal fica reservado exclusivamente ao envelope de erro, para que o agente nunca precise separar log de payload.

Cada invocação gera um **correlation ID** (UUID v4) que aparece no envelope, em todo registro de log e no header `X-Correlation-Id` de cada chamada à API — útil ao acionar o suporte da Conta Azul.

`Authorization`, `client_secret` e corpos contendo tokens são redigidos antes de qualquer escrita em log.

---

## Solução de problemas

**`403` com `"status_conta":"END_TRIAL"`**

```json
{"kind":"client_error","http_status":403,"message":"A conta não está elegível para uso da API devido ao status atual do plano."}
```

Não é um erro do CLI: a autenticação funcionou e a chamada chegou à API. A conta Conta Azul está fora do período de trial e precisa de um plano que habilite acesso à API. Resolva pelo painel da Conta Azul ou com o suporte deles.

**`kind: "auth_failed"` com `invalid_grant`** — o refresh token foi invalidado (consumido por uma rotação concorrente perdida, ou expirado). Rode `ca auth login` novamente.

**Erro ao subir o servidor de callback** — a porta 9876 está ocupada. Libere-a; ela é fixa porque precisa bater com o `redirect_uri` registrado no app.

**Navegador acusa certificado inválido no callback** — rode `mkcert -install` para instalar a CA local no trust store do sistema.

---

## Desenvolvimento

```bash
composer install
vendor/bin/phpunit                                              # testes
vendor/bin/phpstan analyse src/ --level=max --memory-limit=1G   # análise estática
vendor/bin/phpcs                                                # padrões de código (phpcs.xml.dist)
vendor/bin/phpcbf                                               # corrige o que for auto-fixável
```

> O PHPStan estoura o limite default de 128 MB do PHP; passe `--memory-limit=1G`.

### Layout

```
src/
  Command/   comandos Symfony Console
  Api/       cliente HTTP da Conta Azul (retry, polling, paginação)
  Auth/      fluxo OAuth, token store, lock de refresh
  Output/    renderer JSON, envelope de erro, redactor, logger
  Config/    resolução de env vars
  Error/     mapeamento de HTTP para o enum kind
```

### Idioma

Superfície **humana** em pt-BR: README, `--help`, descrições, campo `message` do envelope. Superfície de **máquina** em inglês e estável: valores de `kind`, nomes de campo do envelope, variáveis de ambiente, identificadores e mensagens de commit.

### Limitações conhecidas

- `docs/financial-apis-openapi.yaml` ainda é um **placeholder**: a Conta Azul não publica uma URL estável para a spec OpenAPI, então não há automação de detecção de mudança na API — acompanhamento é manual (veja [CONTRIBUTING.md](CONTRIBUTING.md#acompanhando-mudanças-na-api-da-conta-azul)).

## Contribuição e segurança

Quer contribuir? Veja [CONTRIBUTING.md](CONTRIBUTING.md). Encontrou uma vulnerabilidade? Veja [SECURITY.md](SECURITY.md) — não abra uma issue pública.

## Licença

Apache 2.0. Veja [LICENSE](LICENSE).
