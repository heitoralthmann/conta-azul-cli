<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Categorias

## `categoria list` ✅ { #categoria-list }

`GET /v1/categorias`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Retorna `{itens_totais, itens[]}`. Cada item traz `id`, `nome`, `tipo` (`RECEITA`/`DESPESA`), `categoria_pai` e `entrada_dre`.

## `categoria configuracao-padrao` ✅ { #categoria-configuracao-padrao }

`GET /v1/categorias/configuracao-padrao`

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--sugestao-padrao` / `--no-sugestao-padrao` | não | `--sugestao-padrao` | Inclui (ou omite) a sugestão padrão de categoria em cada item |
| `--no-sugestao-padrao` | não | — | Negate the "--sugestao-padrao" option |

Retorna uma lista de de-para entre operação financeira (`tipo_operacao`, ex: `FRETES_RECEBIDOS`, `JUROS_PAGOS`) e a categoria configurada para ela (`id_categoria`, `nome_categoria`). Com `--no-sugestao-padrao`, o campo `sugestao_padrao` de cada item vem `null`.

## `categoria dre` ✅ { #categoria-dre }

`GET /v1/financeiro/categorias-dre`

Sem parâmetros. Retorna `{itens[]}` com a estrutura hierárquica da DRE (Demonstração do Resultado do Exercício): cada item traz `descricao`, `codigo`, `subitens[]` e `categorias_financeiras[]` associadas.
