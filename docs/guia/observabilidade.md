# Observabilidade

Por padrão o CLI é **silencioso**. Com `--verbose` ou `--debug`, ele grava log estruturado JSONL em `~/.cache/conta-azul-cli/log.jsonl` (rotação em 10 MB, 3 arquivos históricos).

O log **nunca** vai para `stderr` — esse canal fica reservado exclusivamente ao envelope de erro, para que o agente nunca precise separar log de payload.

Cada invocação gera um **correlation ID** (UUID v4) que aparece no envelope, em todo registro de log e no header `X-Correlation-Id` de cada chamada à API — útil ao acionar o suporte da Conta Azul.

`Authorization`, `client_secret` e corpos contendo tokens são redigidos antes de qualquer escrita em log.
