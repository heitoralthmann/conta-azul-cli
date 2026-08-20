<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Baixas

Recurso dedicado de baixa (quitação) de uma parcela — mais rico que o `PATCH` direto de `parcela baixar`: registra data, valor, juros, multa, desconto e método de pagamento. Uma parcela pode ter mais de uma baixa (pagamento parcial). Spec OpenAPI próprio (`acquittance-apis-openapi`), separado do núcleo Financeiro.

## `baixa create` ✅ { #baixa-create }

`POST /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa` — **escrita síncrona** (`200`), sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |
| `--json` | **sim** | Payload JSON da baixa (`data_pagamento`, `conta_financeira` e `composicao_valor` — objeto com `valor_bruto` obrigatório e `multa`/`juros`/`desconto`/`taxa` opcionais — obrigatórios) |

O CLI não valida o conteúdo de `--json`; o schema é o da API. Os três campos
documentados conferem — este spec acertou.

```json
{"data_pagamento":"2026-08-19","conta_financeira":"<uuid>","composicao_valor":{"valor_bruto":0.50}}
```

- **`composicao_valor` aqui, `detalhe_valor` em `conta-a-receber create`.**
  Os dois specs nomeiam o mesmo objeto de formas diferentes na escrita, e
  ambos voltam como `valor_composicao` na leitura.
- **`conta_financeira` é um uuid na escrita e um objeto completo na
  leitura** (`baixa get`, `baixa list`, `parcela get`).
- **Pagamento parcial funciona:** duas baixas de `0,50` numa parcela de
  `1,00` levam a parcela de `PENDENTE` a `RECEBIDO_PARCIAL` e depois a
  `QUITADO`. Cada baixa incrementa a `versao` da parcela.
- **Faltando campo obrigatório, a resposta é um `400` que embrulha uma
  página HTML** ("Unexpected character ('<'…) … Internal Server Error"), não
  uma mensagem de validação. Leia como "falta alguma coisa", sem pista de o
  quê.

## `baixa list` ✅ { #baixa-list }

`GET /v1/financeiro/eventos-financeiros/parcelas/{id}/baixa`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da parcela |

Sem paginação: **array puro**, sem envelope nem contador. As mesmas baixas
também vêm aninhadas em `parcela get` → `baixas[]`.

## `baixa get` ✅ { #baixa-get }

`GET /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |

Traz `valor_composicao` (não `composicao_valor`) e `conta_financeira` como
objeto. Baixa excluída responde `404` **com corpo vazio**.

## `baixa update` ✅ { #baixa-update }

`PATCH /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |
| `--json` | **sim** | Payload JSON com as mudanças; campo `versao` (a versão atual da baixa) é obrigatório |

**Controle de concorrência otimista:** a API exige a `versao` atual no payload e a incrementa após o sucesso, para evitar que duas atualizações concorrentes se sobrescrevam silenciosamente.

> Sem `versao`, a resposta é **`409 Conflict`**, não `400` — "Versão
> informada para o recurso é inválida". A resposta de sucesso usa os nomes
> de **escrita** (`composicao_valor`, `conta_financeira` como uuid), não os
> de leitura.

## `baixa delete` ✅ { #baixa-delete }

`DELETE /v1/financeiro/eventos-financeiros/parcelas/baixa/{id}` — use com cautela: impacta o saldo e o histórico financeiro da parcela associada.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da baixa |

Responde **`200` com corpo vazio** (renderizado como `[]`), não `204` — a
documentação acertou, e o mesmo bug de corpo vazio descrito em
`cobranca delete` também atingia este comando. Corrigido em 2026-08-19.

**A exclusão é permanente e desfaz a quitação.** `baixa get` passa a `404`, e
a parcela volta ao estado anterior: `QUITADO` → `RECEBIDO_PARCIAL` →
`PENDENTE` conforme as baixas somem, com `valor_pago` voltando a `0`. O saldo
da conta financeira volta junto — na verificação, a conta saiu de `35004,34`
e voltou a `35003,34` ao excluir a baixa de `1,00`. **É assim que se desfaz
uma baixa: não existe "estornar".**
