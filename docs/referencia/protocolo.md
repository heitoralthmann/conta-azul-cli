<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Protocolos

## `protocolo get` ✅ { #protocolo-get }

`GET /v1/protocolo/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. O `protocolo` devolvido por uma escrita |

Consulta o status de uma escrita assíncrona. Não tem opções próprias. É como se retoma uma escrita disparada com no-wait, ou uma que terminou em `poll_timeout_known_id` ou `poll_drop_known_id`.

Retorna `{id, resposta, status, evento_financeiro_id}`:

```json
{"id":"60a7011c-…","resposta":"O evento financeiro foi criado no contas a receber da Conta Azul.",
 "status":"SUCCESS","evento_financeiro_id":"eef5550b-…"}
```

> **O id do protocolo vem em `id`, e o da entidade criada em
> `evento_financeiro_id`** — não há um `data` embrulhando o objeto criado.
> Para chegar na parcela criada: `protocolo get` → `evento_financeiro_id` →
> `parcela list <evento>`.
>
> **A escrita que gera o protocolo devolve a chave `protocolo`**, não
> `protocol_id` nem `protocolId`. Até 2026-08-19 o CLI procurava
> `protocolId`, não achava, e por isso **nunca fazia polling**: toda escrita
> assíncrona devolvia o envelope `PENDING` cru, e `--no-wait` e
> `--poll-timeout` não tinham efeito observável. Corrigido.
