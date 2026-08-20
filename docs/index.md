# Conta Azul CLI

CLI em PHP/Symfony que expõe as famílias **Financeiro** (Finanças, Baixas,
Cobranças), **Pessoas**, **Produtos**, **Serviços**, **Contratos**, **Notas
Fiscais**, **Vendas**, **Orçamentos** e **Captura** da API Conta Azul para
consumo por agentes de IA.

!!! warning "Projeto não oficial"

    Este CLI não é mantido, endossado ou afiliado à Conta Azul. É um cliente
    de terceiros para a API pública deles.

!!! note "English"

    Unofficial PHP CLI wrapping the Conta Azul API for consumption by AI
    agents. The Conta Azul API is Brazil-only and its documentation is in
    Portuguese, so this documentation follows suit. The machine-facing
    surface — error `kind` values, envelope field names, environment
    variables — is in English and stable.

## O consumidor primário é um agente

Cada invocação é de curta duração: faz uma chamada, escreve **TOON** em
`stdout` e sai. Toda a complexidade de OAuth2 — fluxo inicial, persistência,
refresh, rotação de token — fica encapsulada dentro do CLI.

Por isso a saída padrão é [TOON](https://github.com/toon-format/toon), mais
compacto em tokens que JSON; o exit code é binário; e os erros vêm em envelope
estruturado com um campo `kind` estável. JSON compacto continua disponível com
`--format=json`.

## Começando

```bash
git clone git@github.com:heitoralthmann/conta-azul-cli.git
cd conta-azul-cli
composer install
./bin/ca list
```

Depois, [configure as credenciais](guia/configuracao.md) e rode
`ca auth login` uma vez.

```bash
ca pessoa list --tamanho-pagina=10
ca conta-a-receber list --data-vencimento-de=2026-08-01 --data-vencimento-ate=2026-08-31
ca parcela get <id> --format=json | jq '.evento'
```

## Por onde seguir

<div class="grid cards" markdown>

- :material-rocket-launch: **[Instalação](guia/instalacao.md)**

    Requisitos, clone e primeira execução.

- :material-key: **[Configuração e autenticação](guia/configuracao.md)**

    Variáveis de ambiente, OAuth2 e o callback HTTPS que a Conta Azul exige.

- :material-console: **[Referência de comandos](referencia/index.md)**

    Os 83 comandos, agrupados pelo endpoint que consomem, com cada parâmetro.

- :material-file-document-alert: **[Notas para quem for estender](guia/estendendo.md)**

    As 25 armadilhas confirmadas da API. Leitura obrigatória antes de mexer
    na integração.

</div>

## Para agentes

A documentação é publicada também em formato legível por máquina:

| Arquivo | Conteúdo |
|---|---|
| [`commands.json`](commands.json) | Manifesto de todos os comandos, argumentos, opções e endpoints |
| [`llms.txt`](llms.txt) | Índice da documentação na convenção [llmstxt.org](https://llmstxt.org) |
| [`llms-full.txt`](llms-full.txt) | A referência inteira em um único arquivo de texto |

Prefira `commands.json` a fazer parsing do Markdown: ele é gerado a partir das
definições do Symfony Console, então acompanha o CLI automaticamente.

## Estado da verificação

Todos os nove grupos do CLI foram exercitados contra a API de produção entre
2026-08-15 e 2026-08-19, e **todos tinham pelo menos um defeito**. Cada
endpoint na referência traz uma marca: **✅ verificado** quer dizer exercitado
contra a API real; **⚠️ não verificado** quer dizer escrito a partir da
documentação e não confiável em nenhum eixo.

O que a campanha ensinou está em
[Notas para quem for estender](guia/estendendo.md).
