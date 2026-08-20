<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Contas financeiras

## `conta-financeira list` ✅ { #conta-financeira-list }

`GET /v1/conta-financeira` — **singular**.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Cada item traz `id`, `nome`, `banco`, `tipo`, `ativo`, `conta_padrao`.

## `conta-financeira saldo` ✅ { #conta-financeira-saldo }

`GET /v1/conta-financeira/{id}/saldo-atual`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--id` | **sim** | ID da conta financeira (de `conta-financeira list`) |

Retorna `{"saldo_atual": 5931.64}`. É opção `--id`, não argumento posicional.
