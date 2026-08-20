<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function array_keys;
use function implode;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_repeat;

/**
 * Renders the two llmstxt.org files the site publishes for machine readers.
 *
 * `llms.txt` is an index an agent can fetch cheaply to find out what exists;
 * `llms-full.txt` concatenates the whole reference for one-shot ingestion.
 * Both point at `commands.json` first, since parsing prose to recover an
 * option list is work no consumer should have to do.
 */
final class LlmsRenderer
{
  /** Width of the rules separating groups in the full text. */
  private const int RULE_WIDTH = 78;

  /**
   * Renders the index file.
   *
   * @param array<string, array<string, mixed>> $fragments
   */
  public function renderIndex(array $fragments): string {
    $out = "# Conta Azul CLI\n\n"
    . "> CLI não oficial em PHP para as APIs da Conta Azul (Financeiro, Pessoas,\n"
    . "> Produtos, Serviços, Contratos, Notas Fiscais, Vendas, Orçamentos e\n"
    . "> Captura). Projetado para consumo por agentes: cada invocação faz uma\n"
    . "> chamada, escreve TOON em stdout e sai com código binário.\n\n"
    . "A saída padrão é TOON, mais compacto em tokens que JSON; `--format=json`\n"
    . "devolve JSON compacto. Erros vão para stderr num envelope estruturado com\n"
    . "um campo `kind` estável.\n\n"
    . "Para a superfície completa em forma de dados, prefira `commands.json` a\n"
    . "fazer parsing destes documentos.\n\n"
    . "## Referência\n\n"
    . "- [Manifesto de comandos (JSON)](commands.json): todos os comandos, argumentos, opções e endpoints\n"
    . "- [Referência completa (texto)](llms-full.txt): esta documentação inteira em um arquivo\n\n"
    . "## Grupos de comandos\n\n";

    foreach ($fragments as $slug => $fragment) {
      $names = [];

      foreach ($fragment['sections'] as $section) {
        foreach ($section['commands'] as $command) {
          $names[] = $command;
        }
      }

      $out .= sprintf(
          "- [%s](referencia/%s.md): %s\n",
          $fragment['title'],
          $slug,
          implode(', ', $names),
      );
    }

    return $out . "\n## Guia\n\n"
    . "- [Contrato de saída](guia/contrato-de-saida.md): formatos, exit codes, envelope de erro e valores de `kind`\n"
    . "- [Opções comuns](guia/opcoes-comuns.md): opções aceitas por todos os comandos\n"
    . "- [Paginação](guia/paginacao.md): limites por listagem e nomes do campo de contagem\n"
    . "- [Datas](guia/datas.md): os dois formatos aceitos e os intervalos obrigatórios\n"
    . "- [Autenticação](guia/autenticacao.md): OAuth2, callback HTTPS e ambientes headless\n"
    . "- [Escritas assíncronas](guia/escritas-assincronas.md): polling, idempotência e retries\n"
    . "- [Notas para quem for estender](guia/estendendo.md): as armadilhas confirmadas da API\n";
  }

  /**
   * Concatenates every group page into one plain-text file.
   *
   * @param array<string, array<string, mixed>> $fragments
   * @param array<string, string>               $pages
   */
  public function renderFull(array $fragments, array $pages): string {
    $rule = str_repeat('=', self::RULE_WIDTH);

    $out = "# Conta Azul CLI — referência completa\n\n"
    . "Gerado por tools/generate-docs.php. Contém a referência de todos os\n"
    . "comandos. Para a superfície em forma de dados, use commands.json.\n\n"
    . $rule . "\n\n";

    foreach (array_keys($fragments) as $slug) {
      $page = $pages['docs/referencia/' . $slug . '.md'] ?? '';
      $page = preg_replace('/^<!--.*?-->\n\n/s', '', $page) ?? $page;

      $out .= rtrim($page) . "\n\n" . $rule . "\n\n";
    }

    return rtrim($out) . "\n";
  }
}
