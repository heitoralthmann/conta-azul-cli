<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Eventos financeiros

## `financeiro alteracoes` ✅ { #financeiro-alteracoes }

`ISO 8601 **sem timezone**`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | início do mês corrente | ISO 8601 **sem timezone** |
| `--data-fim` | não¹ | fim do mês corrente | ISO 8601 **sem timezone** |

Feed de alterações no período — o caminho para reconciliar escritas que terminaram em `ambiguous`.


¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna `{itens_totais, itens[]}`, onde cada item traz apenas o `id` do evento alterado. Use `parcela get` para hidratar.

## `financeiro saldo-inicial` ✅ { #financeiro-saldo-inicial }

`GET /v1/financeiro/eventos-financeiros/saldo-inicial`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | início do mês corrente | ISO 8601 **sem timezone** |
| `--data-fim` | não¹ | fim do mês corrente | ISO 8601 **sem timezone** |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Saldos iniciais das contas financeiras no período.


¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

Retorna `{itens_totais, itens[]}`, com `{tipo, id_conta_financeira, data_competencia, saldo_inicial}` em cada item.

- **O intervalo é limitado a 365 dias.** Acima disso a resposta é `400` ("O
  intervalo entre as datas excede o limite máximo permitido de 365 dias").
  Exatos 365 passam. **`financeiro alteracoes` tem o mesmo teto**, e nenhum
  dos dois documentava isso. O default do CLI (mês corrente) fica bem
  abaixo, então o comando sem argumentos funciona.
- **A janela não filtra por `data_competencia`.** A consulta de 2020 devolve
  um item com `data_competencia` de 2024; janelas diferentes devolvem
  conjuntos distintos, então o filtro existe — só não é sobre o campo que a
  resposta mostra.
- Descarta em silêncio parâmetro desconhecido, como as outras listagens
  (comprovado com `zzz_bogus=abc`: o total não muda). Paginação funciona e
  aceita `tamanho_pagina` até `1000`.
