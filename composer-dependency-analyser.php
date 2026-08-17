<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// bin/ca has no .php extension (PHP_CodeSniffer needs the same accommodation,
// see composer.json's lint:bin script), so the default composer.json autoload
// scan never sees it.
$config->addPathToScan(__DIR__ . '/bin/ca', false);

// ext-posix is intentionally in "suggest", not "require": every call to it is
// guarded, and Windows doesn't have it (see README's Requisitos section and
// ci.yml's Windows extensions comment).
$config->ignoreErrorsOnExtension('ext-posix', [ErrorType::SHADOW_DEPENDENCY]);

// ext-mbstring and ext-openssl are required by Symfony components internally
// (Console formatting, HTTPS transport) without this codebase calling their
// functions directly, so static usage scanning can't see the need.
$config->ignoreErrorsOnExtensions(['ext-mbstring', 'ext-openssl'], [ErrorType::UNUSED_DEPENDENCY]);

return $config;
