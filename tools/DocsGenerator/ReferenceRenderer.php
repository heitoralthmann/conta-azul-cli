<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function reset;
use function rtrim;
use function sprintf;

/**
 * Renders the Markdown pages of the command reference.
 *
 * Each group becomes one page; each page is a sequence of sections. A section
 * usually documents one command, but several commands share one when the API
 * makes them inseparable — `pessoa get` and `pessoa legado` differ only in
 * which id they take.
 */
final class ReferenceRenderer
{
  /** Wires the renderer to its collaborators. */
  public function __construct(
      private readonly Anchor $anchor,
      private readonly ParameterTable $parameterTable,
  ) {
  }

  /**
   * Renders one group page.
   *
   * @param array<string, mixed>                $fragment
   * @param array<string, array<string, mixed>> $definitions
   */
  public function renderGroup(array $fragment, array $definitions): string {
    $out = $this->banner() . '# ' . $fragment['title'] . "\n\n";

    if (isset($fragment['intro'])) {
      $out .= rtrim($fragment['intro']) . "\n\n";
    }

    foreach ($fragment['sections'] as $section) {
      $out .= $this->renderSection($section, $definitions);
    }

    return rtrim($out) . "\n";
  }

  /**
   * Renders the quick-reference page listing every command and its endpoint.
   *
   * @param array<string, array<string, mixed>> $fragments
   */
  public function renderIndex(array $fragments): string {
    $out = $this->banner()
    . "# Referência de comandos\n\n"
    . "Todos os comandos do `ca`, agrupados pelo endpoint que consomem.\n\n"
    . "Cada endpoint traz uma marca de confiança:\n\n"
    . "- **✅ verificado** — exercitado contra a API real e respondeu como documentado.\n"
    . "- **⚠️ não verificado** — escrito a partir da documentação e nunca exercitado.\n"
    . "  Não leia como \"provavelmente certo\": leia como **não confiável**.\n\n"
    . "!!! danger \"O que `⚠️` realmente significa\"\n\n"
    . "    A marca não quer dizer só \"a escrita nunca foi disparada\". Ela quer\n"
    . "    dizer **não confiável em todos os eixos**: path, nome de filtro, nome\n"
    . "    de campo do payload, tipo do id e formato da resposta. Os filtros\n"
    . "    listados numa seção `⚠️` saíram da documentação, não de uma chamada\n"
    . "    real.\n\n"
    . "    Isso não é pessimismo de ofício: a campanha de verificação exercitou\n"
    . "    os nove grupos do CLI contra a produção e **todos tinham pelo menos um\n"
    . "    defeito** — filtros que devolviam a coleção inteira fingindo filtrar,\n"
    . "    campos obrigatórios que a documentação não lista, um comando apontado\n"
    . "    para o endpoint errado, e um caso que passava em qualquer teste\n"
    . "    razoável. O apanhado está em\n"
    . "    [Notas para quem for estender](../guia/estendendo.md), e é leitura\n"
    . "    obrigatória antes de mexer em qualquer integração.\n\n"
    . "!!! info \"Esta página é gerada\"\n\n"
    . "    A tabela sai das definições do Symfony Console, então ela não pode\n"
    . "    divergir do que o CLI realmente aceita. A prosa de cada grupo é\n"
    . "    curada em `docs/_data/commands/`. Convenção de leitura: `obrig.` marca\n"
    . "    o que falha sem valor; `padrão` é o que o CLI assume quando você\n"
    . "    omite.\n\n"
    . "| Comando | Endpoint | |\n|---|---|---|\n";

    foreach ($fragments as $slug => $fragment) {
      foreach ($fragment['sections'] as $section) {
        $mark = $section['status'] === 'unverified' ? '⚠️' : '✅';

        foreach ($section['commands'] as $command) {
          $out .= sprintf(
              "| [`%s`](%s.md#%s) | `%s` | %s |\n",
              $command,
              $slug,
              $this->anchor->forCommand($command),
              $section['endpoints'][$command] ?? '— (local)',
              $mark,
          );
        }
      }
    }

    return $out;
  }

  /**
   * Renders one section: heading, endpoints, parameter table and curated prose.
   *
   * @param array<string, mixed>                $section
   * @param array<string, array<string, mixed>> $definitions
   */
  private function renderSection(array $section, array $definitions): string {
    $commands   = $section['commands'];
    $unverified = $section['status'] === 'unverified';

    $heading = $section['title'] ?? implode(', ', array_map(
        static fn (string $command): string => '`' . $command . '`',
        $commands,
    ));

    $out = '';

    // Extra anchors so every command in a shared section stays directly linkable.
    foreach (array_slice($commands, 1) as $command) {
      $out .= '<a id="' . $this->anchor->forCommand($command) . '"></a>' . "\n";
    }

    $out .= '## ' . $heading . ($unverified ? ' ⚠️' : ' ✅')
    . ' { #' . $this->anchor->forCommand($commands[0] ?? $heading) . " }\n\n"
    . $this->renderEndpoints($section);

    if ($unverified) {
      $out .= "!!! warning \"Não verificado\"\n\n"
      . "    Escrito a partir da documentação e **nunca exercitado** contra a\n"
      . "    API. Path, nomes de filtro, campos do payload, tipo do id e formato\n"
      . "    da resposta são todos não confiáveis. Veja\n"
      . "    [Notas para quem for estender](../guia/estendendo.md).\n\n";
    }

    $out .= $this->parameterTable->render($section, $definitions);

    if (isset($section['body'])) {
      $out .= rtrim($section['body']) . "\n\n";
    }

    return $out;
  }

  /**
   * Renders the endpoint line, or list, plus any curated note about it.
   *
   * @param array<string, mixed> $section
   */
  private function renderEndpoints(array $section): string {
    $endpoints = $section['endpoints'] ?? [];
    $note      = $section['endpoint_note'] ?? null;

    if (count($endpoints) === 1) {
      // With a single endpoint the note reads as the tail of the same sentence.
      $endpoint = '`' . reset($endpoints) . '`';

      return $note !== null ? $endpoint . ' — ' . $note . "\n\n" : $endpoint . "\n\n";
    }

    if ($endpoints === []) {
      return $note !== null ? $note . "\n\n" : '';
    }

    $out = '';

    foreach ($endpoints as $command => $endpoint) {
      $out .= '- `' . $command . '` — `' . $endpoint . "`\n";
    }

    $out .= "\n";

    return $note !== null ? $out . $note . "\n\n" : $out;
  }

  /** Marks a file as generated so nobody edits it by hand. */
  private function banner(): string {
    return "<!-- Gerado por tools/generate-docs.php. Não edite à mão.\n"
    . "     Prosa e endpoints: docs/_data/commands/*.yaml -->\n\n";
  }
}
