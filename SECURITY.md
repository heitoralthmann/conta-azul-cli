# Política de segurança

## Versões suportadas

O projeto ainda está pré-1.0 (`0.x`). Apenas a última versão publicada recebe correções de segurança.

## Reportando uma vulnerabilidade

**Não abra uma issue pública.** Use o [Private Vulnerability Reporting](../../security/advisories/new) do GitHub — o formulário cria um relatório visível só para o mantenedor até que a correção esteja pronta.

Inclua, na medida do possível:

- Descrição do problema e impacto (ex.: vazamento de token, injeção, bypass de validação)
- Passos para reproduzir, ou um PoC mínimo
- Versão/commit afetado

O objetivo é confirmar o recebimento em até 7 dias e, uma vez confirmada a vulnerabilidade, publicar uma correção antes de divulgar detalhes publicamente.

## Fora de escopo

Este CLI é um **cliente não oficial** da API Conta Azul (veja o aviso no [README](README.md)). Vulnerabilidades na API, no painel ou na infraestrutura da própria Conta Azul não são deste repositório — reporte-as diretamente ao canal de segurança da Conta Azul.

## Superfície sensível deste projeto

Áreas onde uma vulnerabilidade teria mais impacto: persistência e refresh do token OAuth2, o redactor de segredos na saída/log, e o parsing de entrada vindo de flags/env vars.
