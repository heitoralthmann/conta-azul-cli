<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Contas a receber

## `conta-a-receber list` ✅ { #conta-a-receber-list }

`GET /v1/financeiro/eventos-financeiros/contas-a-receber/buscar`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-vencimento-de` | não¹ | 1º dia do mês corrente | Vencimento inicial (`YYYY-MM-DD`) |
| `--data-vencimento-ate` | não¹ | último dia do mês corrente | Vencimento final (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

> **O `id` de cada item é o da PARCELA, não do evento financeiro.** É esse valor que `parcela get` e `parcela baixar` consomem. O id do evento vem aninhado em `evento.id` na resposta de `parcela get`.

Cada item traz `id`, `status` (`ACQUITTED`, `OVERDUE`, …), `status_traduzido`, `total`, `pago`, `nao_pago`, `data_vencimento`, `data_competencia`, `descricao`, `categorias[]`, `centros_de_custo[]`, `cliente`. A resposta ainda inclui um bloco `totais` com somatórios por situação.

## `conta-a-receber create` ✅ { #conta-a-receber-create }

`POST /v1/financeiro/eventos-financeiros/contas-a-receber` — **escrita assíncrona**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--json` | **sim** | — | Payload JSON, repassado à API **verbatim** |
| `--poll-timeout` | não | `60` | Segundos aguardando a confirmação assíncrona |
| `--no-wait` | não | — | Retorna o protocolo na hora, sem aguardar |

O CLI não valida o conteúdo de `--json`; o schema é o da API.

**Payload mínimo aceito** — descoberto exercitando, porque a documentação
oficial erra em dois pontos (ver adiante):

```json
{
  "descricao": "…",
  "data_competencia": "2026-08-19",
  "valor": 1.00,
  "rateio": [{"id_categoria": "<uuid>", "valor": 1.00, "rateio_centro_custo": []}],
  "condicao_pagamento": {"parcelas": [{
    "descricao": "…",
    "data_vencimento": "2026-09-30",
    "detalhe_valor": {"multa":0,"juros":0,"valor_bruto":1.00,"valor_liquido":1.00,"desconto":0,"taxa":0}
  }]}
}
```

Armadilhas confirmadas:

- **Este endpoint acusa todos os campos faltantes de uma vez**, diferente do
  resto da API, que revela um por `400`. O primeiro `400` já lista
  `competenceDate`, `valor`, `condicao_pagamento` e `rateio` — em
  *camelCase*, com o nome do campo Java, não o nome JSON (`data_competencia`).
- **`condicao_pagamento` não é o que a leitura devolve.** Na escrita é
  `{parcelas: [...]}`, uma lista; `parcela get` devolve
  `{quantidade_parcelas, montante_fixo}`, um resumo. Mandar o formato de
  leitura dá `paymentCondition.installments: deve ter no mínimo uma parcela`.
- **A composição de valor da parcela é `detalhe_valor` na escrita** e
  `valor_composicao` em toda leitura. `composicao_valor` — o nome que as
  Baixas usam — **não** funciona aqui.
- **A documentação oficial marca `observacao`, `contato` e `conta_financeira`
  como obrigatórios; não são.** O payload acima é aceito sem os três.
- **`contato` é obrigatório na prática se você for gerar cobrança.** Sem ele
  `cobranca create` recusa com "Existem parcelas associadas a eventos
  financeiros sem identificação do pagador" — e, como não há `PUT`/`PATCH`
  de evento financeiro, não dá para adicionar o pagador depois. Decida antes
  de criar.
- **A resposta é `200`, não o `202` documentado**, e traz
  `{protocolo, status, data_criacao}` com `status` `PENDING`.

> **Não existe como desfazer.** A API não publica `DELETE` nem `GET` de
> evento financeiro (`/v1/financeiro/eventos-financeiros/{id}` responde o
> `404` genérico de rota inexistente em ambos). Conta a receber criada por
> engano só sai pela interface web.
