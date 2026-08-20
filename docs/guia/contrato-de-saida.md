# Contrato de saída

Vale para **todos** os comandos estruturados (`auth login` / `auth logout`
escrevem texto para o operador, não este envelope):

| Canal | Conteúdo |
|---|---|
| `stdout` | Só o payload. Padrão: **TOON**. JSON compacto com `--format=json`. |
| `stderr` | Envelopes de erro e de aviso, no **mesmo** formato do stdout. |
| exit code | `0` sucesso, `1` falha. Binário. |

`--json` nas escritas é payload de **entrada**. Não escolhe o formato de saída.

> **Resposta sem corpo sai como lista vazia, não objeto vazio.** Um `204 No
> Content` — e também um `{}` vindo da API — é decodificado para um array PHP
> vazio, serializado como `[]` em JSON e como lista vazia em TOON. Quem
> consome com `jq` (logo `--format=json`) deve tratar `[]` como "nenhum conteúdo"
> nos comandos marcados `204` (`pessoa patch`, `pessoa excluir`,
> `contrato delete`, `orcamento excluir-lote`, `captura recusar`,
> `nota-fiscal vincular-mdfe`).

**Envelope de erro** (stderr, exit 1), padrão TOON:

```
correlation_id: a1b2…
http_status: 404
kind: client_error
message: …
protocol_id: null
retryable: false
```

**Envelope de aviso** (stderr, exit 0 — a operação teve sucesso):

```
kind: warning
message: Intervalo de vencimento não informado por completo; usando 2026-08-01 a 2026-08-31. …
```

## Valores de `kind`

| `kind` | Significado | `retryable` |
|---|---|---|
| `client_error` | Requisição inválida (4xx). Corrija os argumentos. | `false` |
| `auth_failed` | Falha de autenticação. Rode `ca auth login`. | `false` |
| `rate_limited` | Limite de requisições atingido. | `true` |
| `transient` | Erro de rede ou 5xx transitório. | `true` |
| `server_error` | Erro do servidor não recuperável. | `false` |
| `ambiguous` | Resposta perdida após envio. **A escrita pode ter sido aplicada** — reconcilie via `financeiro alteracoes`. | `false` |
| `poll_timeout_known_id` | Escrita aceita, polling estourou. Retome com `protocolo get <protocol_id>`. | `false` |
| `poll_drop_known_id` | Polling interrompido. Retome com `protocolo get <protocol_id>`. | `false` |

Quando `kind` é `ambiguous`, `poll_timeout_known_id` ou `poll_drop_known_id`, **não reenvie a escrita cegamente**: o CLI não deduplica. Use o `protocol_id` do envelope.
