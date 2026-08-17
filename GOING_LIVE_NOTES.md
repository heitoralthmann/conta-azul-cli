# Notas de go-live

> **O repositório ainda está privado.** Este arquivo é um checklist para o
> dia em que ele for publicado — não um relatório do que já foi feito. Os
> itens de código do plano de padronização (P0-P3) estão todos prontos e
> mesclados em `main`; o que resta é só clique na UI do GitHub e do
> Packagist, nesta ordem.

## 1. Tornar o repositório público

Settings → General → rolar até "Danger Zone" → **Change repository
visibility** → Public.

Na mesma página do repo, clique na engrenagem ao lado de "About" e
preencha:

- **Descrição:** CLI PHP não oficial para a API Conta Azul (Financeiro,
  Pessoas, Produtos, Serviços), pensado para uso por agentes de IA.
- **Tópicos:** `php`, `cli`, `contaazul`, `api-client`, `oauth2`,
  `symfony-console`, `brazil`
- **Homepage:** já preenchida via `composer.json` (aponta pro próprio
  repo); ajuste se quiser apontar pra outro lugar.

Antes de tornar público, vale um `git log -p` rápido em busca de
credencial colada em mensagem de commit ou fixture — `.env` e `.certs/`
nunca foram versionados, mas não custa conferir.

## 2. Ativar Private Vulnerability Reporting

Settings → Security → **Code security and analysis** → "Private
vulnerability reporting" → Enable.

**Só fica disponível depois do item 1** (repo público, ou GitHub Advanced
Security). `SECURITY.md` já está escrito assumindo que essa opção existe.

## 3. Proteger a branch `main`

Settings → Branches → **Add branch protection rule** → branch `main`.

- Require a pull request before merging
- Require status checks to pass before merging — marcar os checks de
  `tests.yml`, `static-analysis.yml`, `code-style.yml` e `security.yml`
  (aparecem na lista depois da primeira execução em um PR)
- Require branches to be up to date before merging
- Bloquear force-push e deleção da branch

## 4. Publicar no Packagist

Pré-requisitos já prontos:

- Pacote renomeado para `heitoralthmann/conta-azul-cli` ✅
- `CHANGELOG.md` existe ✅
- Tag `v0.1.0` e release publicados ✅

Passos:

1. packagist.org → Submit → cole a URL do repo GitHub (exige repo
   público, item 1)
2. Conectar a conta do GitHub ao Packagist (ou configurar o webhook
   manualmente em Settings → Webhooks do repo, apontando pra
   `https://packagist.org/api/github?username=heitoralthmann`) pra
   atualização automática a cada push de tag
3. Conferir que a página gerada no Packagist bate com a descrição e os
   badges do README

---

Depois desses quatro passos, os badges de Packagist (Version, Downloads)
podem ser adicionados ao README — hoje ficaram de fora de propósito porque
ainda apontariam pra uma página inexistente.
