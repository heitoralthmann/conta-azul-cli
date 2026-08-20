<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use RuntimeException;

use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;

/**
 * Assembles every generated documentation file and writes what changed.
 *
 * Nothing here decides content; it wires the loaders, the validator and the
 * renderers together, then either writes the result or reports which files a
 * commit forgot to regenerate.
 */
final class DocsBuilder
{
  /** Wires the builder to its collaborators. */
  public function __construct(
      private readonly string $root,
      private readonly DefinitionLoader $definitionLoader,
      private readonly FragmentLoader $fragmentLoader,
      private readonly Validator $validator,
      private readonly ReferenceRenderer $referenceRenderer,
      private readonly ManifestRenderer $manifestRenderer,
      private readonly LlmsRenderer $llmsRenderer,
  ) {
  }

  /** Builds a wired instance for a project root. */
  public static function forRoot(string $root): self {
    return new self(
        $root,
        new DefinitionLoader($root),
        new FragmentLoader($root),
        new Validator($root),
        new ReferenceRenderer(new Anchor(), new ParameterTable()),
        new ManifestRenderer($root),
        new LlmsRenderer(),
    );
  }

  /**
   * Renders every generated file, keyed by path relative to the project root.
   *
   * @return array<string, string>
   *
   * @throws RuntimeException When the curated notes disagree with the CLI.
   */
  public function build(): array {
    $definitions = $this->definitionLoader->load();
    $fragments   = $this->fragmentLoader->load();

    $errors = $this->validator->validate($definitions, $fragments);

    if ($errors !== []) {
      throw new RuntimeException(
          sprintf("A referência divergiu do código (%d problemas):\n\n  - ", count($errors))
          . implode("\n  - ", $errors)
          . "\n\nCorrija docs/_data/commands/*.yaml ou o comando correspondente.",
      );
    }

    $files = [];

    foreach ($fragments as $slug => $fragment) {
      $files['docs/referencia/' . $slug . '.md'] = $this->referenceRenderer->renderGroup($fragment, $definitions);
    }

    $files['docs/referencia/index.md'] = $this->referenceRenderer->renderIndex($fragments);
    $files['docs/commands.json']       = $this->manifestRenderer->render($fragments, $definitions);
    $files['docs/llms.txt']            = $this->llmsRenderer->renderIndex($fragments);
    $files['docs/llms-full.txt']       = $this->llmsRenderer->renderFull($fragments, $files);

    return $files;
  }

  /**
   * Returns the paths whose contents on disk differ from the rendered output.
   *
   * @param array<string, string> $files
   *
   * @return list<string>
   */
  public function stale(array $files): array {
    $stale = [];

    foreach ($files as $path => $contents) {
      $absolute = $this->root . '/' . $path;
      $existing = is_file($absolute) ? file_get_contents($absolute) : null;

      if ($existing === $contents) {
        continue;
      }

      $stale[] = $path;
    }

    return $stale;
  }

  /**
   * Writes the rendered files to disk, creating directories as needed.
   *
   * @param array<string, string> $files
   */
  public function write(array $files): void {
    foreach ($files as $path => $contents) {
      $absolute  = $this->root . '/' . $path;
      $directory = dirname($absolute);

      if (! is_dir($directory)) {
        mkdir($directory, 0o755, true);
      }

      file_put_contents($absolute, $contents);
    }
  }
}
