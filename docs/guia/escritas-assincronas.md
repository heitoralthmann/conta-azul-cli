# Escritas assíncronas

Quando a API responde `202 + protocolId`, o CLI faz **polling interno** (backoff de 1 s dobrando até 8 s, teto total de 60 s) até o status virar terminal, e só então retorna. Uma invocação equivale a sucesso atômico ou falha atômica.

- `--poll-timeout=<segundos>` ajusta o teto
- `--no-wait` retorna o `202` cru imediatamente, sem polling

Com `--no-wait`, cabe ao agente consultar `ca protocolo get <id>` depois.

## Idempotência

O CLI **não** deduplica escritas, **não** retorna respostas em cache e **não** retenta `POST` em erro de transporte. Dedupe automático sobre hash de body seria um footgun em contexto financeiro — dois pagamentos legítimos idênticos seriam silenciosamente colapsados em um.

A contrapartida: em `kind: "ambiguous"`, **a reconciliação é responsabilidade do agente**, comparando entidade + valor + data via `ca financeiro alteracoes --data-inicio=<...> --data-fim=<...>`.

## Retries

- **GET:** retry em erro de transporte, 429, 502, 503 e 504 — backoff de 0,5 s / 2 s / 8 s com jitter de ±20 %, máximo de 3 tentativas. Honra `Retry-After`.
- **POST/PUT/PATCH/DELETE:** retry **apenas** em 429, que garante que a requisição não foi processada.

## Limites de taxa

A Conta Azul limita **10 req/s** e **600 req/min** por tenant. O CLI não implementa throttle client-side: cabe ao agente não disparar mais de 10 invocações concorrentes contra o mesmo tenant. Excedentes recebem 429 e são retentadas conforme a política acima.
