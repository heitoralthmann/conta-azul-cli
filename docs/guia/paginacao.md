# Paginação

Onde há `--pagina` / `--tamanho-pagina`:

| Parâmetro | Padrão | Valores aceitos |
|---|---|---|
| `--pagina` | `1` | Qualquer inteiro positivo |
| `--tamanho-pagina` | `50` | **`10`, `20`, `50`, `100`, `200`, `500`, `1000`** — qualquer outro é rejeitado localmente, sem chamar a API |

**Três listagens param em `100`**, e a validação local sabe disso desde
2026-08-19:

| Listagem | Máximo |
|---|---|
| `servico list`, `nota-fiscal list`, `nota-fiscal-servico list` | `100` |
| todas as outras | `1000` |

Nessas três, `--tamanho-pagina 200` falha na hora com
`Valores aceitos: 10, 20, 50, 100`, sem gastar uma ida à API. Antes a
validação usava a lista larga para todo mundo, então o `200` passava aqui e
voltava `400` de lá — justamente o que validar localmente deveria evitar.

**E uma listagem não usa lista discreta nenhuma.** `captura status` aceita
**qualquer inteiro de 1 a 20** — `1`, `5` e `15` respondem `200`, e só `21`
para cima responde `400` ("O valor do atributo 'tamanho_pagina' deve ser no
máximo 20"). Medido em 2026-08-19, depois que um id de documento real
finalmente permitiu chamar o endpoint. Validá-lo contra os degraus discretos
errava **nos dois sentidos**: deixava `1000` chegar na API (que recusa) e
recusava `15` (que a API aceita). Por isso a validação passou a distinguir a
*forma* da regra, não só o teto — veja `PageSizeRule`.

| Listagem | Regra |
|---|---|
| `captura status` | qualquer inteiro em `1..20` |
| todas as outras | os degraus discretos acima |

Os limites foram **medidos** endpoint a endpoint contra a produção, não
deduzidos: aceitam `1000` as listagens de produtos (e seus catálogos),
pessoas, vendas, itens de venda, orçamentos, contratos, transferências,
contas a pagar/receber, categorias, centros de custo e contas financeiras.

E o nome do campo de contagem **não é o mesmo em todo lugar**:

| Formato | Onde |
|---|---|
| `{itens_totais, itens[]}` | comandos financeiros |
| `{totalItems, items[]}` | `pessoa list`, `produto list` |
| `{total_items, items[]}` | catálogos de `produto` (categorias, cest, ncm, …) |
| `{itens[], paginacao{total_itens}}` | `servico list`, `nota-fiscal list`, `nota-fiscal-servico list` |
| `{totais, quantidades, total_itens, itens[]}` | `venda list` |
| `{itens[], itens_totais, totais}` | `venda itens` |
| `{itens[], total_itens}` | `orcamento list` |
| `{itens_totais, itens[]}` | `contrato list` |
| `{itens[], paginacao{pagina_atual, tamanho_pagina, total_itens, total_paginas}}` | `captura status` |

`venda` sozinha traz duas dessas variações — a listagem e os itens de uma
venda usam formatos diferentes. E `contrato list` mostra por que nem o nome
da chave se deduz: estava documentado como `{itens_totais, items[]}` e a API
devolve `itens`, não `items`.

E o campo de contagem **pode simplesmente estar errado**: o `total_itens` de
`orcamento list` ignora os orçamentos em `ORCAMENTO_RECUSADO` que a mesma
resposta devolve dentro de `itens`. Ao verificar uma listagem, conte
`len(itens)` em vez de confiar no total — foi só assim que essa divergência
apareceu.
