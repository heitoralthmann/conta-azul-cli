# Especificação — Conta Azul CLI

**Versão:** 0.1.0 (rascunho)
**Data:** 2026-05-27
**Audiência:** desenvolvedores e operadores do wrapper CLI da API Conta Azul
**Consumidor primário do CLI:** agente de IA (não humano)

---

## 1. Visão geral

CLI em PHP/Symfony que expõe endpoints da família Financeiro da API Conta Azul (Finanças, Baixas, Cobranças) para invocação por um agente de IA. Cada invocação é de curta duração, retorna JSON compacto em stdout e sinaliza o resultado por código de saída binário acompanhado de envelope JSON estruturado em stderr quando há erro. Toda a complexidade de OAuth2 — fluxo inicial, armazenamento, refresh, rotação, expiração — é encapsulada dentro do CLI.

---

## 2. Escopo

**Decisão.** O CLI cobre exclusivamente a família Financeiro da API Conta Azul (Finanças + Baixas + Cobranças). Demais famílias (Pessoas, Produtos, Vendas, Contratos, Notas Fiscais) ficam fora.

**Justificativa.** Reduz a superfície de mapeamento para um único arquivo OpenAPI (`financial-apis-openapi.yaml`), elimina dois landmines identificados na pesquisa (gap de status na NF-e e sistema duplo de IDs UUID + `id_legado`), e o único endpoint de delta da API (`/financeiro/eventos-financeiros/alteracoes`) está justamente nessa família — o que beneficia diretamente o caso de uso de reconciliação.

**Trade-off aceito.** O landmine mais grave da API — escritas assíncronas sem `Idempotency-Key` — permanece concentrado neste escopo. A simplificação é de superfície, não de design.

**Em aberto.** "Baixas" e "Cobranças" são tratadas neste documento como verbos sobre primitivos do Financeiro (parcelas, contas a receber), não como famílias de endpoint próprias. Confirmar lendo o YAML completo se há endpoints dedicados `/baixas` ou `/cobrancas` que não apareceram na pesquisa documental.

---

## 3. Filosofia de comandos — pass-through fino (Opção A)

**Decisão.** Comandos espelham 1:1 os endpoints da API Conta Azul. Sem abstrações de domínio que escondam quirks da plataforma.

**Justificativa.** O CLI pode ser mantido contra o YAML OpenAPI com mínimo de drift; o agente é capaz de raciocinar sobre os padrões nativos da plataforma; o escopo da família Financeiro é pequeno o suficiente para que o agente lide com as particularidades diretamente, sem precisar de uma camada de tradução semântica no wrapper.

**Trade-off aceito.** O agente carrega o ônus de orquestrar polling de `protocolId` (mitigado pela decisão A2, seção 7), de reconciliar escritas ambíguas (mitigado por I1 + endpoint de delta, seções 8 e 23), e de respeitar particularidades pontuais da plataforma.

**Em aberto.** Nenhum.

---

## 4. Superfície de comandos

**Decisão.** Taxonomia `ca <substantivo> <verbo> [args]`. Substantivos em português espelhando a API; verbos em inglês (idioma idiomático do Symfony Console) por padrão, exceto quando o verbo é um conceito de domínio sem equivalente limpo em inglês (notadamente `baixar`). Comandos de infraestrutura do CLI (`auth login`, `auth logout`, `--help`, `--version`) permanecem em inglês.

**Exemplos representativos:**

- `ca auth login`
- `ca auth logout`
- `ca lancamento list --pagina 1 --tamanho-pagina 50`
- `ca lancamento get <id>`
- `ca conta-a-receber create --json '{...}'`
- `ca conta-a-pagar create --json '{...}'`
- `ca parcela get <id>`
- `ca parcela baixar <id> --valor 100.00 --data 2026-05-27`
- `ca cobranca list`
- `ca conta-financeira list`
- `ca conta-financeira saldo --id <id>`
- `ca categoria list`
- `ca centro-de-custo list`
- `ca transferencia create --json '{...}'`
- `ca financeiro alteracoes --desde 2026-05-26T00:00:00-03:00`
- `ca protocolo get <id>` (consulta de status de escrita assíncrona quando o agente optou por `--no-wait`)

**Justificativa.** Consistência com a API onde ela é a fonte de verdade (substantivos, parâmetros); consistência com o framework onde ele é a fonte de verdade (verbos genéricos de CRUD, infraestrutura).

**Trade-off aceito.** Há mistura visual de português e inglês em uma mesma linha de comando. É menos elegante esteticamente, mas evita tradução desnecessária e mantém os nomes de parâmetros idênticos aos da documentação oficial da Conta Azul — o que reduz o atrito ao cruzar referências entre a doc oficial e o CLI.

**Em aberto.** Lista exata e completa de comandos por endpoint do `financial-apis-openapi.yaml` deve ser enumerada na fase de implementação.

---

## 5. Formato de saída

**Decisão.** **JSON compacto** (sem espaços em branco, sem indentação, sem quebras de linha) em stdout para toda resposta bem-sucedida. Sem campos derivados pelo wrapper; o payload é repassado conforme retornado pela API Conta Azul, modulo o desempacotamento padrão de objeto raiz quando aplicável.

**Justificativa.** O consumidor é um agente de IA; JSON é o formato em que todo modelo treinado é nativo; JSON compacto fecha uma fração significativa do gap de tokens contra formatos alternativos. No próprio benchmark do TOON, JSON-compacto vence TOON em configurações profundamente aninhadas.

**Trade-off aceito.** Abandonamos a redução de tokens de ~40% anunciada pelo TOON no caso ótimo (arrays tabulares uniformes). Em compensação, eliminamos um serializador customizado em PHP, um formato externo de seis meses sem implementação PHP de referência, e toda uma categoria de bugs de round-trip e quoting.

**Em aberto.** Nenhum.

---

## 6. Modelo de erros e códigos de saída

**Decisão.** Código de saída **binário**: `0` em sucesso, `1` em qualquer erro. Em erro, stderr contém **um único objeto JSON compacto** e nada mais. Em sucesso, stderr fica vazio.

**Esquema do envelope de erro:**

```
{
  "kind": "<enum>",
  "retryable": <bool>,
  "http_status": <int|null>,
  "protocol_id": "<string|null>",
  "correlation_id": "<uuid v4>",
  "message": "<string em pt-BR>"
}
```

**Enum `kind`** — contrato estável (mudanças = bump major):

| `kind` | Significado | `retryable` |
|---|---|---|
| `client_error` | 4xx exceto 429. Requisição malformada; corrigir antes de retentar. | `false` |
| `transient` | Erro de transporte antes de a requisição chegar à API. Seguro re-executar a chamada. | `true` |
| `ambiguous` | Resposta perdida no fio após o envio. A escrita pode ter sido aplicada. **Reconciliar via `alteracoes`.** | `false` |
| `auth_failed` | Refresh falhou ou credenciais inválidas. Operador deve rodar `ca auth login`. | `false` |
| `rate_limited` | 429 da API. Aguardar `Retry-After` (quando presente) ou aplicar backoff. | `true` |
| `poll_timeout_known_id` | A2 atingiu o teto sem status terminal. `protocol_id` presente. Consultar manualmente via `ca protocolo get`. | `false` |
| `poll_drop_known_id` | Polling interrompido por erro de transporte; `protocol_id` presente. Retomar polling. | `true` |
| `server_error` | 5xx em GET após esgotar retries. Em escritas, 5xx é mapeado para `ambiguous`. | depende |

**Justificativa.** O agente despacha sobre `kind`, não sobre código numérico. Novos `kind` podem ser adicionados sem realocar códigos de saída ou quebrar parsing existente. Manter logs e mensagens humanas fora de stderr garante que o envelope nunca seja contaminado por linhas espúrias.

**Trade-off aceito.** stderr fica reservado exclusivamente para o envelope. Mensagens de progresso e logs de debug precisam de outro canal (ver seção 17).

**Em aberto.** O texto exato das mensagens em pt-BR para cada `kind` será definido na implementação; o enum em si é o contrato.

---

## 7. Semântica de escritas assíncronas — A2 (implicit block-and-poll)

**Decisão.** Quando a API retorna `202 + protocolId` (criação de eventos financeiros e similares), o CLI faz **polling interno com backoff** até o status atingir terminal (`SUCCESS` ou `ERROR`) ou estourar o timeout. Só então retorna ao agente.

**Política de polling:**

- Backoff inicial 1 s, dobrando até teto por-poll de 8 s.
- Teto total padrão: **60 s**.
- Override por flag: `--poll-timeout <segundos>` para ajustar o teto, `--no-wait` para retornar o 202 cru imediatamente sem polling.

**Estados terminais possíveis vistos pelo agente:**

- Sucesso terminal (`SUCCESS`): exit `0`, stdout traz o payload final.
- Falha terminal (`ERROR`): exit `1`, envelope `kind: "server_error"` (ou outro `kind` quando a API discrimina).
- Timeout (`PENDING` ao fim do teto): exit `1`, envelope `kind: "poll_timeout_known_id"` com `protocol_id` populado.
- Polling interrompido por erro de transporte com `protocolId` já conhecido: exit `1`, envelope `kind: "poll_drop_known_id"` com `protocol_id` populado.

**Justificativa.** Uma única invocação do CLI corresponde a sucesso atômico ou falha atômica do ponto de vista do agente. LLMs raciocinam consideravelmente melhor sobre esse modelo do que sobre um workflow de dois estágios (POST + polling separado coordenado pelo agente).

**Trade-off aceito.** Cada poll conta contra o orçamento de 10 rps / 600 rpm por tenant; escritas longas seguram o processo do CLI aberto; comportamento não é estritamente 1:1 com a API. Aceitável dada a magnitude da melhoria de UX para o agente.

**Em aberto.** A distribuição real de tempos `PENDING → SUCCESS` em produção deve ser verificada contra o tenant de desenvolvimento para confirmar que 60 s cobre o p99.

---

## 8. Idempotência — I1 (sem dedupe client-side)

**Decisão.** O CLI não armazena fingerprints de escritas, não retorna respostas em cache, **não retenta POSTs em erro de transporte**.

**Justificativa.** Dedupe client-side automático sobre hash de body é um footgun em contexto financeiro — dois pagamentos legítimos idênticos seriam silenciosamente colapsados em um. Dedupe via chave fornecida pelo agente acrescentaria estado persistente e um contrato extra que o agente teria que respeitar — complexidade não justificada neste escopo.

**Trade-off aceito.** Em caso de falha ambígua (`kind: "ambiguous"`), o agente é responsável pela reconciliação via `/financeiro/eventos-financeiros/alteracoes`, comparando entidade + valor + data dentro de uma janela conhecida. Risco residual: duplicação de evento financeiro se o agente não reconciliar.

**Em aberto.**

- Latência do feed `alteracoes` entre uma escrita aceita e o evento aparecer ali. Se for significativamente >1 s, o agente precisa de retry/espera na etapa de reconciliação. **Verificar no tenant dev.**
- Se a Conta Azul deduplica server-side em re-POSTs idênticos. **Verificar no tenant dev.** Se sim, o risco prático de duplicação cai bastante.

---

## 9. Política de retries e rate limiting

**Decisão.**

- **GET:** retry em erro de transporte / 429 / 502 / 503 / 504, com backoff exponencial (0.5 s, 2 s, 8 s) + jitter de ±20 %, máximo de 3 tentativas. Honra `Retry-After` quando presente.
- **POST / PUT / PATCH / DELETE:** retry **apenas** em 429 (seguro, pois 429 significa que a requisição não foi processada). Erros de transporte e 5xx em escritas resultam em `kind: "ambiguous"` ou `kind: "server_error"` sem retry automático.
- **Sem throttle client-side.** O agente controla concorrência; o servidor + retry de 429 absorvem rajadas.

**Justificativa.** Consistente com I1. Retentar uma escrita que pode ter sido aplicada é exatamente o caso que I1 se recusa a tratar automaticamente.

**Trade-off aceito.** O agente precisa saber quando paralelizar (≤ 10 rps por tenant). Limite documentado no README.

**Em aberto.** Nomes exatos dos headers que a Conta Azul retorna em 429. A documentação menciona headers de rate limit mas não os nomeia. Verificar empiricamente.

---

## 10. Paginação

**Decisão.** Flags `--pagina` e `--tamanho-pagina` mapeiam 1:1 para os parâmetros `pagina` e `tamanho_pagina` da API. **Sem auto-paginação.** **Sem flag `--all`.**

**Validação.** `--tamanho-pagina` deve ser um dos valores aceitos pela API (`10`, `20`, `50`, `100`, `200`, `500`, `1000`); qualquer outro valor resulta em `kind: "client_error"` antes mesmo de a requisição sair do CLI.

**Justificativa.** Consistente com a filosofia de pass-through. O agente pagina explicitamente; o CLI não esconde latência ou consumo de quota dentro de loops invisíveis.

**Trade-off aceito.** O agente precisa loopear paginação por conta própria. Aceitável dada a filosofia.

**Em aberto.** Nenhum.

---

## 11. Ciclo de vida OAuth2

**Decisão geral.** Fluxo Authorization Code, único suportado pela Conta Azul. Sem PKCE, device flow ou client_credentials — a API não oferece nenhum desses.

### 11.1. Fluxo inicial — `ca auth login`

1. CLI gera `state` aleatório.
2. CLI inicia listener HTTP local em `http://localhost:9876/callback` (porta fixa, pré-registrada no app na Conta Azul).
3. CLI imprime a URL de autorização e pede ao usuário que abra em um navegador.
4. Usuário completa login no IdP (AWS Cognito, por trás da Conta Azul).
5. O redirect captura `code`; CLI valida `state` e troca por tokens via `POST https://auth.contaazul.com/oauth2/token` com `Authorization: Basic base64(client_id:client_secret)`.
6. CLI persiste tokens; listener encerra; processo sai com `0`.

**Justificativa.** Loopback local é o padrão estabelecido para OAuth em CLIs e funciona em qualquer máquina dev com navegador. PHP tem suporte nativo via `stream_socket_server`, dispensando dependências adicionais.

**Trade-off aceito.** Porta fixa (`9876`). Se já estiver ocupada, o login falha com mensagem clara em pt-BR. Aceitável.

### 11.2. Ambientes headless (CI)

Operador roda `ca auth login` uma vez em máquina dev, depois copia o refresh token resultante para o ambiente CI via env var `CA_BOOTSTRAP_REFRESH_TOKEN`. Na primeira invocação no ambiente CI, o CLI detecta a env var, executa um refresh, persiste o token resultante em arquivo e prossegue. Após o primeiro arranque, a env var pode ser removida — o refresh subsequente vem do arquivo persistido.

### 11.3. Refresh

- **Pré-emptivo:** se o access token tiver < 60 s de TTL restante, refresh antes da requisição.
- **Reativo:** em 401 da API, uma única tentativa de refresh seguida de retry único da chamada original. Se o refresh falhar, `kind: "auth_failed"`.
- **Rotação:** refresh tokens rotacionam a cada uso. O CLI **sempre** persiste o novo refresh token retornado.

### 11.4. Single-flight de refresh

`flock()` sobre o arquivo de tokens durante a operação de refresh. Invocações concorrentes esperam o lock, depois leem o token atualizado em vez de tentar refresh em paralelo. Isto resolve diretamente a corrida de rotação documentada no Cognito.

### 11.5. Falhas de refresh

- `invalid_grant`: refresh token inválido ou consumido por outra rotação concorrente perdida. Envelope `kind: "auth_failed"`; mensagem orienta a rodar `ca auth login` novamente.
- Erros de rede no endpoint de refresh: `kind: "transient"` — seguro retentar, pois refresh é idempotente do ponto de vista do servidor.

### 11.6. Multi-tenant

**Conta única.** Sem `--profile` na primeira versão. Adicionar depois é não-breaking (basta introduzir `--profile <nome>` ou `CA_CLI_PROFILE`, com o comportamento atual virando o profile `default`).

**Trade-off aceito.** Se o usuário operar mais de uma empresa na Conta Azul, precisa de instâncias separadas do CLI (variando `HOME` ou `CA_CLI_TOKEN_PATH`).

**Em aberto.** Ambigüidade na documentação da Conta Azul sobre se o refresh token expira por inatividade independentemente do TTL nominal de 5 anos. **Verificar no tenant dev.**

---

## 12. Armazenamento de tokens

**Decisão.** Arquivo único em `~/.config/conta-azul-cli/tokens.json`, modo `0600`. Path overridável via env var `CA_CLI_TOKEN_PATH`.

**Forma do conteúdo:**

```
{
  "access_token": "<string>",
  "access_token_expires_at": "<ISO 8601 UTC>",
  "refresh_token": "<string>",
  "refresh_token_obtained_at": "<ISO 8601 UTC>",
  "token_type": "Bearer"
}
```

**Justificativa.** Simples, cross-platform, sem dependências de keychain. Permissões `0600` mitigam acesso por outros usuários da máquina; o conteúdo tem sensibilidade equivalente a um cookie persistente de sessão de browser.

**Trade-off aceito.** Sem proteção contra exfiltração do home do usuário. Se isso for inaceitável em algum ambiente, o usuário pode apontar `CA_CLI_TOKEN_PATH` para um filesystem cifrado.

**Em aberto.** Nenhum.

---

## 13. Configuração e segredos

**Precedência (do mais alto para o mais baixo):**

1. Flag de CLI.
2. Variável de ambiente.
3. Arquivo `.env` (carregado apenas em dev via Symfony Dotenv).
4. Defaults compilados.

**Variáveis de ambiente reconhecidas:**

| Variável | Obrigatória | Significado / default |
|---|---|---|
| `CA_CLIENT_ID` | sim | client_id do app Conta Azul |
| `CA_CLIENT_SECRET` | sim | client_secret do app |
| `CA_REDIRECT_URI` | não | default `http://localhost:9876/callback` |
| `CA_API_BASE_URL` | não | default `https://api-v2.contaazul.com` |
| `CA_AUTH_BASE_URL` | não | default `https://auth.contaazul.com` |
| `CA_CLI_TOKEN_PATH` | não | default `~/.config/conta-azul-cli/tokens.json` |
| `CA_BOOTSTRAP_REFRESH_TOKEN` | não | bootstrap único para ambientes headless |

**Versionado no repositório:** `.env.example` com placeholders. **Nunca versionados:** `.env`, `tokens.json`.

**Justificativa.** Padrão Symfony; precedência alinhada ao princípio "o mais específico vence".

**Trade-off aceito.** `CA_BOOTSTRAP_REFRESH_TOKEN` em env var significa que um refresh token pode aparecer em scanners de segredo de CI ou logs de deploy. Mitigação: usar apenas no primeiro arranque, persistir em arquivo no host CI e remover a env var subsequentemente.

**Em aberto.** Nenhum.

---

## 14. Arquitetura Symfony

**Componentes utilizados:**

- `symfony/console` — superfície CLI.
- `symfony/http-client` — cliente HTTP para a Conta Azul.
- `symfony/dependency-injection` — container.
- `symfony/dotenv` — `.env` em dev.
- `symfony/filesystem` — manipulação de arquivos auxiliares.

**Não utilizados (e por quê):**

- `symfony/cache` — overkill para um único arquivo de tokens.
- `symfony/messenger` — sem mensageria; CLI é de curta duração.
- `symfony/lock` — uma única chamada a `flock()` PHP nativa cobre o caso; não vale a dependência.
- `symfony/http-foundation` — o listener OAuth é um socket cru via `stream_socket_server`, não justifica um stack HTTP completo.

**Camadas / layout de pastas:**

```
src/
  Command/        # comandos Symfony Console (verbo-substantivo)
  Api/            # cliente Conta Azul (uma classe por grupo de recurso)
  Auth/           # fluxo OAuth, token store, lock de refresh
  Output/         # serializador JSON, renderer de envelope, redactor
  Config/         # resolução de env + flags
  Error/          # mapeamento de exceções HTTP para `kind`
```

**Justificativa.** Mínimo de Symfony necessário para o trabalho; cada componente faz uma coisa identificável.

**Trade-off aceito.** Algum boilerplate que `symfony/lock` poderia ter substituído — aceitável dado o único call-site.

**Em aberto.** Nenhum.

---

## 15. Mapeamento da API Conta Azul

**Decisão.** Mapeamento **escrito à mão**, não codegen. Uma classe por grupo de recurso do `financial-apis-openapi.yaml`, com métodos espelhando os operations da spec.

**Justificativa.** O escopo (~ 30–50 endpoints) é pequeno o suficiente para que codegen seja uma cerimônia desproporcional; código escrito à mão é mais legível, mais testável e mais simples de manter.

**Vigilância contra drift.** Job de CI agendado que faz fetch periódico do `financial-apis-openapi.yaml` e diffa contra a cópia anexada ao repositório. Diff não-vazio abre uma issue automaticamente. Sem auto-merge.

**Trade-off aceito.** Novos endpoints ou mudanças de schema exigem ação humana. Aceitável dado o ritmo histórico de mudança da API e o escopo limitado deste CLI.

**Em aberto.** Nenhum.

---

## 16. Estratégia de testes

**Decisão.**

- **Unit:** PHPUnit. Cobertura para: mapeamento de envelope, transição de exit codes, single-flight de refresh, validação de parâmetros de paginação, parser/builder de URLs, redactor de segredos.
- **Integração:** `MockHttpClient` do Symfony + fixtures JSON em `tests/fixtures/`. Sem VCR de terceiro.
- **OAuth:** servidor HTTP local que responde respostas canônicas ao endpoint de token; testa fluxo completo de login, refresh, rotação, falha de refresh.
- **Smoke (manual ou agendado):** suite reduzida que roda contra o tenant de desenvolvimento real. Não bloqueante (o tenant expira a cada 30 dias e pode estar inválido em qualquer momento).

**Não automatizado.** Travamento do `flock` entre processos é difícil de simular em PHPUnit single-process; deixar para inspeção manual ou integration test dedicado fora do pipeline padrão.

**Justificativa.** PHPUnit é idiomático para PHP/Symfony; `MockHttpClient` é nativo do framework; fixtures simples são suficientes para o escopo limitado.

**Trade-off aceito.** Sem VCR significa que adicionar um novo endpoint exige criar fixtures manualmente. Aceitável.

**Em aberto.** Nenhum.

---

## 17. Observabilidade

**Decisão.**

- **Default: silencioso.** Sucesso → JSON em stdout, stderr vazio. Erro → envelope JSON em stderr, stdout vazio.
- **`--verbose` ou `--debug`:** ativa log estruturado em arquivo JSONL em `~/.cache/conta-azul-cli/log.jsonl`. **Nunca em stderr** — stderr fica reservado para o envelope.
- **Correlation ID:** UUID v4 por invocação, incluído em todo registro de log e no campo `correlation_id` do envelope. Em cada chamada à API Conta Azul, o ID é também enviado em header `X-Correlation-Id` para facilitar suporte.
- **Redaction:** nunca logar `Authorization`, corpos contendo tokens, `client_secret`, `CA_BOOTSTRAP_REFRESH_TOKEN`. Lista mantida em `src/Output/Redactor.php` e aplicada uniformemente em todo log e mensagem de erro.
- **Rotação:** `log.jsonl` rotaciona em 10 MB, mantém 3 arquivos históricos.

**Justificativa.** stderr exclusivo para o envelope evita ambigüidade na borda agente↔CLI; arquivo de log é trivial e cross-platform.

**Trade-off aceito.** Debug interativo exige `tail -f` sobre o arquivo. Aceitável — o consumidor primário é agente.

**Em aberto.** Nenhum.

---

## 18. Empacotamento e distribuição

**Decisão.**

- **Primário:** PHAR único construído com Box, publicado em GitHub Releases.
- **Secundário:** `composer global require contaazul-cli/cli` (essencialmente gratuito ao publicar no Packagist).
- **Adiado:** imagem Docker — adicionar quando houver caso de uso CI explicitamente solicitado.

**Justificativa.** PHAR roda em qualquer máquina com PHP na versão mínima; Composer global é canal alternativo natural para devs PHP que já têm o ambiente.

**Trade-off aceito.** PHAR exige PHP instalado na máquina alvo. Para ambientes sem PHP, Docker resolverá no futuro.

**Em aberto.** Versão mínima exata de PHP — alvo é a última estável; bumpar conforme as versões maiores forem saindo.

---

## 19. CI/CD

**Decisão.**

- **GitHub Actions.**
- **Matriz de PHP:** apenas a última versão estável (sem matriz multi-versão).
- **Análise estática:** PHPStan no nível máximo. Pint ou PHP-CS-Fixer para estilo. **Sem Psalm** (redundante com PHPStan no max).
- **Release:** tag SemVer → CI builda PHAR → publica em GitHub Releases → atualiza Packagist via webhook.

**Versionamento.** SemVer estrito. Para um consumidor agente, quebra contratual e portanto bump **major** inclui:

- Mudança em valores ou semântica de exit codes.
- Mudança em schema do envelope de erro (novo campo obrigatório, remoção de campo, mudança de tipo).
- Renomeação de comandos, flags ou argumentos posicionais.
- Mudança de comportamento default (ex.: A2 virar fire-and-forget; auto-pagination ligada por default).
- Remoção ou renomeação de qualquer valor do enum `kind`.

Adição de novos valores ao `kind` é considerada minor — o contrato é que o agente deve aceitar valores desconhecidos como erro genérico.

**Justificativa.** Uma única versão PHP é a meta declarada; PHPStan no máximo é o teto razoável de garantia estática; SemVer é a única linguagem que o agente pode contratar contra.

**Trade-off aceito.** Zero suporte para versões PHP anteriores. Aceitável dado o escopo e a posição declarada.

**Em aberto.** Janela de deprecation antes de remover um `kind`: sugestão de 1 release minor com o `kind` ainda emitido porém marcado como deprecated no log de verbose, depois remoção no major seguinte.

---

## 20. Implantação e escalabilidade

**Decisão.** Sempre **CLI de curta duração**, nunca daemon. Cada invocação spawna um processo, faz seu trabalho, sai.

**Estado compartilhado.** Apenas o arquivo de tokens. `flock()` é a única primitiva de concorrência usada pelo CLI.

**Comportamento sob carga:**

- **1000 chamadas/hora** = ~17/min = bem dentro do limite de 600 rpm por tenant da Conta Azul.
- **Concorrência:** o rate limit por tenant é 10 rps. O agente é responsável por não disparar mais de 10 chamadas concorrentes contra o mesmo tenant. O CLI **não** implementa throttle.
- **Múltiplos tenants:** fora do escopo da primeira versão (ver seção 11.6).

**Justificativa.** Stateless é o modelo mais simples; `flock` cobre o único caso de contenção real (rotação de refresh).

**Trade-off aceito.** Se o agente disparar > 10 invocações concorrentes, as excedentes recebem 429 e retentam via política da seção 9. Aceitável.

**Em aberto.** Nenhum.

---

## 21. Manutenibilidade

**Decisão.** Mapeamento manual da API com job de CI vigiando drift do YAML OpenAPI (seção 15). Sem geração automática de código.

**Cadência sugerida.** Revisão trimestral da spec da Conta Azul (changelog manual + diff do YAML pinado, complementado pelo alerta automatizado).

**Deprecation de endpoint da Conta Azul.** Se a Conta Azul deprecar um endpoint que o CLI expõe, o comando correspondente ganha aviso no `--help` em pt-BR e é removido na próxima major do CLI.

**Justificativa.** A API Conta Azul não possui política de deprecation pública. O CLI assume a responsabilidade de monitorar mudanças.

**Trade-off aceito.** Dependência de vigilância humana sobre mudanças não anunciadas pela Conta Azul. Aceitável dado o escopo limitado.

**Em aberto.** Nenhum.

---

## 22. Idioma

**Decisão.**

- **Superfície humana (pt-BR):** README, guia de instalação, `--help`, descrições de comando e flag, campo `message` do envelope, este documento de especificação.
- **Superfície de máquina (inglês, estável):** valores do enum `kind`, nomes de campos do envelope JSON, valores de variáveis de ambiente, identificadores e comentários no código, mensagens de commit, branches.

**Justificativa.** Humanos brasileiros operam o CLI; agentes despacham sobre campos estáveis em inglês. Misturar os dois quebraria simultaneamente UX humana e contrato de máquina.

**Trade-off aceito.** Parte do CLI mostra mistura visual de português e inglês (substantivos pt, verbos en, parâmetros pt). Aceitável e consciente.

**Em aberto.** Nenhum.

---

## 23. Riscos e incógnitas

Itens não resolvidos pela documentação pública ou pela pesquisa. Cada um deve ser fechado por experimento contra o tenant de desenvolvimento antes do release 1.0.

| Risco | O que resolveria |
|---|---|
| **Latência do feed `alteracoes`** entre escrita aceita e evento visível. Impacta a estratégia de reconciliação sob `kind: "ambiguous"`. | Cronometrar p50/p95/p99 escrevendo eventos no dev tenant e medindo até aparecerem em `/financeiro/eventos-financeiros/alteracoes`. |
| **Dedupe server-side em re-POSTs idênticos.** Se a Conta Azul deduplica, o risco prático de I1 cai bastante. Se não, é exatamente o que assumimos. | Repetir `POST /v1/financeiro/eventos-financeiros/contas-a-receber` com body idêntico e observar se gera um ou dois eventos. |
| **Distribuição de tempo `PENDING → SUCCESS`.** O teto de 60 s do A2 cobre o p99? | Coletar amostras no dev tenant; tunar o default se necessário. |
| **Refresh token expira por inatividade?** A documentação é ambígua entre "5 anos" e "5 anos ou até o próximo refresh". | Deixar um refresh token sem uso por períodos crescentes e tentar usá-lo. |
| **Nomes exatos dos headers de rate limit.** Documentação menciona mas não nomeia os headers. | Estourar o limite no dev tenant e inspecionar os headers da resposta 429. |
| **Forma exata do envelope de erro por endpoint.** Sub-especificado nas docs; diferentes endpoints emitem schemas distintos. | Provocar 400/401/429/500 em cada endpoint do escopo e catalogar shapes. |
| **Endpoints dedicados de "Baixas" e "Cobranças"?** A pesquisa não encontrou famílias `/baixas` ou `/cobrancas`; este documento assume que são verbos sobre `parcelas` / `contas-a-receber`. | Reler `financial-apis-openapi.yaml` integralmente; se houver endpoints dedicados, expandir seção 4. |
| **Meios de pagamento (boleto/PIX) acoplados a cobranças.** Se cobrança envolve geração de boleto/PIX, podem existir endpoints relacionados não cobertos pela pesquisa documental. | Reler `financial-apis-openapi.yaml` por menções a boleto, PIX, gateway. |
| **Rotação do tenant dev a cada 30 dias.** Suite de smoke baseada em tenant live pode quebrar silenciosamente. | Solicitar à Conta Azul renovação programada do tenant dev, ou agendar lembretes operacionais. |
| **Comportamento de `--no-wait`** em escritas síncronas (não-202). | Confirmar que a flag é no-op em endpoints que não retornam 202; documentar explicitamente. |

---

**Fim do documento.**
