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
        'concat_space' => ['spacing' => 'one'],
        'ordered_imports' => true,
        'blank_line_before_statement' => ['statements' => ['return', 'exit','throw', 'continue', 'break']],
        'types_spaces' => ['space' => 'none'],
    ])
    ->setFinder($finder)
;
