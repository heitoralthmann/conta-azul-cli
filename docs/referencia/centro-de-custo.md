<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Centros de custo

## `centro-de-custo list` ✅ { #centro-de-custo-list }

`GET /v1/centro-de-custo` — note o **singular** no path.

| Parâmetro | Obrig. | Padrão | Descrição |
|---|---|---|---|
| `--pagina` | não | `1` | Número da página |
| `--tamanho-pagina` | não | `50` | Itens por página |

Cada item traz `id`, `codigo`, `nome`, `ativo`.

## `centro-de-custo create` ✅ { #centro-de-custo-create }

`POST /v1/centro-de-custo` — **escrita síncrona** (`200`), sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON do centro de custo (`nome` obrigatório; `codigo` opcional) |

Retorna `{id, codigo, nome, ativo}`. `codigo` omitido volta `null` — os
registros criados pela interface trazem `""`, não `null`.

> **Não existe como desfazer.** A API publica só `GET` e `POST` para este
> recurso: `DELETE`, `PUT` e `PATCH` em `/v1/centro-de-custo/{id}` respondem
> o `404` genérico de rota inexistente. Centro de custo criado por engano só
> sai pela interface web.
