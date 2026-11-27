<?php

use PhpCsFixer\Finder;
use Redaxo\PhpCsFixerConfig\Config;

$finder = new Finder()
    ->in([__DIR__ . '/src', __DIR__ . '/kits', __DIR__ . '/pages', __DIR__ . '/examples', __DIR__ . '/tools'])
    ->exclude(['video'])
;

return Config::redaxo6()
    ->setFinder($finder)
;
