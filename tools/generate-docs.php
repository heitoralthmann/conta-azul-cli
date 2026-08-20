<?php

/**
 * Entry point for the documentation generator.
 *
 * The mechanical half of the command reference — names, arguments, options,
 * defaults — is read back out of the running CLI, so it cannot drift from the
 * code. The half no introspection can know — which endpoint a command calls,
 * whether it was verified against the real API, and every trap found while
 * exercising it — lives in docs/_data/commands/*.yaml and is merged on top.
 *
 * Usage:
 *   php tools/generate-docs.php           writes the generated files
 *   php tools/generate-docs.php --check   fails if the files are out of date
 */

declare(strict_types=1);

use ContaAzulCli\Tools\DocsGenerator\DocsBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

$builder   = DocsBuilder::forRoot(dirname(__DIR__));
$checkOnly = in_array('--check', $_SERVER['argv'] ?? [], true);

try {
  $files = $builder->build();
} catch (Throwable $exception) {
  fwrite(STDERR, $exception->getMessage() . "\n");

  exit(1);
}

$stale = $builder->stale($files);

if ($checkOnly) {
  if ($stale !== []) {
    fwrite(STDERR, "A documentação gerada está desatualizada:\n\n  - " . implode("\n  - ", $stale) . "\n");
    fwrite(STDERR, "\nRode `composer docs:generate` e faça commit do resultado.\n");

    exit(1);
  }

  fwrite(STDERR, "Documentação gerada está em dia.\n");

  exit(0);
}

$builder->write($files);

fwrite(STDERR, sprintf("%d arquivos gerados (%d atualizados).\n", count($files), count($stale)));

exit(0);
