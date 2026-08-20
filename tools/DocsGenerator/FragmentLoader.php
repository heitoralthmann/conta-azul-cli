<?php

declare(strict_types=1);

namespace ContaAzulCli\Tools\DocsGenerator;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function is_file;
use function sprintf;

/**
 * Loads the curated notes that introspection cannot supply.
 *
 * One YAML file per command group holds the endpoint each command calls, its
 * verification mark, and the prose written while exercising the API. The
 * companion `_order.yaml` fixes the order the reference presents the groups
 * in, which is neither alphabetical nor derivable.
 */
final class FragmentLoader
{
  /** Binds the loader to a project root. */
  public function __construct(private readonly string $root) {
  }

  /**
   * Returns every fragment, keyed by group slug, in presentation order.
   *
   * @return array<string, array<string, mixed>>
   *
   * @throws RuntimeException When a group listed in `_order.yaml` has no file.
   */
  public function load(): array {
    $directory = $this->root . '/docs/_data/commands';
    $order     = Yaml::parseFile($directory . '/_order.yaml')['groups'];
    $fragments = [];

    foreach ($order as $slug) {
      $path = $directory . '/' . $slug . '.yaml';

      if (! is_file($path)) {
        throw new RuntimeException(sprintf('Fragmento ausente: %s', $path));
      }

      $fragments[$slug] = Yaml::parseFile($path);
    }

    return $fragments;
  }
}
