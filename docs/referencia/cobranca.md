<!-- Gerado por tools/generate-docs.php. Não edite à mão.
     Prosa e endpoints: docs/_data/commands/*.yaml -->

# Cobranças

Gera cobrança (boleto, PIX ou link de pagamento) para a parcela de uma conta a receber. Spec OpenAPI próprio (`charge-apis-openapi`), separado do núcleo Financeiro.

## `cobranca create` ✅ { #cobranca-create }

`POST /v1/financeiro/eventos-financeiros/contas-a-receber/gerar-cobranca` — **escrita síncrona** (`200`), sem protocolo.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `--json` | **sim** | Payload JSON da cobrança (`conta_bancaria`, `descricao_fatura`, `id_parcela`, `data_vencimento` e `tipo` — `LINK_PAGAMENTO`, `PIX_COBRANCA` ou `BOLETO` — obrigatórios) |

O CLI não valida o conteúdo de `--json`; o schema é o da API. Os cinco campos
documentados conferem. Retorna `{id, url, status}`, mas:

- **`url` vem `null` na criação**, com `status` `AGUARDANDO_CONFIRMACAO`. O
  link só existe depois que o provedor confirma — use `cobranca get` para
  buscá-lo, não a resposta da criação.
- **`conta_bancaria` precisa ser conta de banco.** Apontar para uma
  `CAIXINHA` devolve `400` "O tipo de conta selecionado não é válido para a
  criação de cobrança".
- **A parcela precisa ter pagador.** Evento financeiro criado sem `contato`
  recusa com "Existem parcelas associadas a eventos financeiros sem
  identificação do pagador", e não há como acrescentá-lo depois.
- **Payload incompleto responde `500`, não `400`** — e o CLI traduz `500` em
  escrita para `ambiguous`, mandando reconciliar. Na verificação de
  2026-08-19 nada tinha sido criado: `parcela get` devolveu
  `solicitacoes_cobrancas: []`. É o mesmo problema de classificação de
  `contrato get`/`delete`/`encerrar`.
- Cliente sem CPF/e-mail leva a cobrança a `INVALIDO` alguns segundos depois
  de criada. O endpoint funcionou; quem recusou foi o provedor.

## `cobranca get` ✅ { #cobranca-get }

`GET /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}`

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da cobrança |

Retorna `{id, url, status}`. Id inexistente responde `404` **com corpo
vazio** — a mensagem do envelope de erro fica em branco.

As cobranças de uma parcela também aparecem em
`parcela get` → `solicitacoes_cobrancas[]`, com mais campos
(`status_solicitacao_cobranca`, `tipo_solicitacao_cobranca`, `valor_composicao`).

## `cobranca delete` ✅ { #cobranca-delete }

`DELETE /v1/financeiro/eventos-financeiros/contas-a-receber/cobranca/{id}` — recomendado só para cobrança gerada incorretamente ou a invalidar antes do pagamento.

| Parâmetro | Obrig. | Descrição |
|---|---|---|
| `<id>` | **sim** | Argumento posicional. Uuid da cobrança |

Responde **`200` com corpo vazio** (renderizado como `[]`), não `204` — a
documentação acertou. Até 2026-08-19 isso **quebrava o CLI**: o caso de corpo
vazio dependia do status ser `204`, então `toArray()` estourava numa exceção
que escapava do tratamento de erro, e um delete bem-sucedido imprimia a linha
de uso do Symfony e saía com código `1`. Corrigido.

**A exclusão é lógica e assíncrona.** A cobrança passa a
`EM_CANCELAMENTO` e assenta em `CANCELADO`; `cobranca get` continua
respondendo `200`. Logo depois do `DELETE` há uma janela em que o `get`
devolve `404` — é transitório, não indica exclusão permanente. Como em
`servico delete`, o teste confiável é o **status**, não o `404`.
