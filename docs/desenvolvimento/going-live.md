# Notas de go-live

Checklist dos passos que **só existem na UI do GitHub e do Packagist** — nenhum
deles é alcançável por CLI ou API enquanto o repositório for privado. Todo o
trabalho de código está mesclado em `main`.

A ordem importa: os passos 3 e 4 são literalmente indisponíveis antes do passo
1. No plano Free, a API responde
`403 Upgrade to GitHub Pro or make this repository public`.

## 1. Tornar o repositório público

Settings → General → "Danger Zone" → **Change repository visibility** → Public.

Na página do repo, engrenagem ao lado de "About":

- **Descrição:** CLI PHP não oficial para a API Conta Azul (Financeiro,
  Pessoas, Produtos, Serviços, Contratos, Notas Fiscais, Vendas, Orçamentos,
  Captura), pensado para uso por agentes de IA.
- **Tópicos:** `php`, `cli`, `contaazul`, `api-client`, `oauth2`,
  `symfony-console`, `brazil`
- **Homepage:** apontar para o site de documentação depois do passo 2.

!!! note "Auditoria de histórico já feita"

    Em 2026-08-20, antes da publicação, os 132 commits foram varridos:
    `.env`, `.certs/` e `tokens.json` nunca foram versionados; nenhum padrão
    de credencial (JWT, chave AWS, chave privada, bearer) aparece no
    histórico; e cada valor do `.env` real foi testado contra o histórico
    inteiro e contra todos os arquivos rastreados, sem nenhuma
    correspondência. Vale repetir a varredura se algo for commitado às
    pressas antes de publicar.

## 2. Habilitar o GitHub Pages

Settings → Pages → **Source: GitHub Actions**.

Até isso acontecer, o workflow `docs.yml` constrói e valida o site mas **pula**
o deploy, emitindo um aviso no resumo do run — de propósito, para que o CI não
fique vermelho por um motivo que não é código. Depois de habilitar, o próximo
push na `main` publica sozinho, sem mudança de workflow.

O site sai em `https://heitoralthmann.github.io/conta-azul-cli/`.

## 3. Ativar Private Vulnerability Reporting

Settings → Security → **Code security and analysis** → "Private vulnerability
reporting" → Enable.

`SECURITY.md` já aponta para o formulário; o link fica morto até aqui.

## 4. Proteger a branch `main`

Settings → Branches → **Add branch protection rule** → `main`.

- Require a pull request before merging
- Require status checks to pass before merging — marcar `Tests`,
  `Static Analysis`, `Code Style`, `Security` e `Reference is in sync`
  (aparecem na lista depois da primeira execução em um PR)
- Require branches to be up to date before merging
- Bloquear force-push e deleção da branch

!!! warning "Isso encerra o push direto na `main`"

    O fluxo usado até aqui — commit e push direto — deixa de funcionar. A
    partir desse ponto, toda mudança passa por PR. Vale ligar depois de o
    ritmo de mudanças assentar, não no mesmo dia da publicação.

## 5. Publicar no Packagist

1. packagist.org → Submit → colar a URL do repo (exige o passo 1)
2. Conectar a conta do GitHub ao Packagist, ou configurar o webhook em
   Settings → Webhooks apontando para
   `https://packagist.org/api/github?username=heitoralthmann`, para
   atualização automática a cada push de tag
3. Conferir que a página gerada bate com a descrição e os badges do README

Depois disso, os badges de Packagist (Version, Downloads) podem entrar no
README — ficaram de fora de propósito porque apontariam para página
inexistente.

## Sobre a numeração

O projeto segue em `0.x` de propósito. `1.0.0` compromete a superfície pública
— nomes de comando e de opção, formato de saída, enum `kind`, exit codes — a
só quebrar em major. Os dois únicos commits `!:` do histórico são de
2026-08-20; vale deixar passar um intervalo sem quebra, com uso real, antes de
assumir esse compromisso.
