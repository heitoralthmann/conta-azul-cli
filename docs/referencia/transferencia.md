<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Transferências

## `transferencia list` ✅ { #transferencia-list }

`GET /v1/financeiro/transferencias`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--data-inicio` | não¹ | 1º dia do mês corrente | Início do intervalo (`YYYY-MM-DD`) |
| `--data-fim` | não¹ | último dia do mês corrente | Fim do intervalo (`YYYY-MM-DD`) |
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

¹ A API exige o intervalo; o CLI supre com o mês corrente e avisa em stderr.

> As datas vão em `YYYY-MM-DD` puro, diferente de `financeiro alteracoes` (mesmo domínio `/financeiro/`), que exige o instante ISO 8601 completo.

Cada item traz `id`, `descricao`, `valor`, `data`, e os blocos `origem`/`destino` com `data`, `composicao_valor` (`valor_bruto`, `juros`, `multa`, `valor_liquido`, `desconto`, `taxa`) e `conta_financeira` (`id`, `nome`, `instituicao_bancaria`).
