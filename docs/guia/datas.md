# Datas

Dois formatos, e eles não são intercambiáveis:

| Contexto | Formato | Exemplo |
|---|---|---|
| Vencimento e baixa | `YYYY-MM-DD` | `2026-08-01` |
| `financeiro alteracoes` | ISO 8601 **sem timezone** | `2026-08-01T00:00:00` |

> Em `alteracoes`, sufixo `Z` ou offset (`-03:00`) faz a API responder **400**.

Onde há intervalo de datas, a API o **exige**. Omitir as opções não causa erro: o CLI assume o **mês corrente** e avisa em stderr qual recorte aplicou. Informar ambas as opções silencia o aviso. Exceção: as **duas listagens de notas fiscais** (`nota-fiscal list` e `nota-fiscal-servico list`), cujo intervalo é limitado a **15 dias** pela API — o default nelas são os **últimos 15 dias**, não o mês corrente.
