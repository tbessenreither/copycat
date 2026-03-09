<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'no_unused_imports' => true,
        'modifier_keywords' =>  ['elements' => ['const', 'method']],
    ])
    ->setFinder($finder)
;
