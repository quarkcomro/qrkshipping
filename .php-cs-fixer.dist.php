<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use PrestaShop\CodingStandards\CsFixer\Config;

$finder = Finder::create()
    ->files()
    ->name('*.php')
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/scripts',
    ])
    ->append([__DIR__ . '/qrkshipping.php']);

return (new Config())
    ->setFinder($finder)
    ->setUsingCache(false);
