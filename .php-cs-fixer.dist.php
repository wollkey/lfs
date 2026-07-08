<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/public')
    ->in(__DIR__ . '/bin');

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@PHP8x5Migration' => true,

        'strict_param' => true,
        'declare_strict_types' => true,

        'yoda_style' => [
            'equal' => false,
            'identical' => false,
        ],

        'phpdoc_to_comment' => [
            'allow_before_return_statement' => true,
        ],
        'phpdoc_line_span' => [
            'const' => 'multi',
            'property' => 'multi',
            'method' => 'multi',
            'function' => 'multi',
        ],
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'remove_inheritdoc' => true,
            'allow_unused_params' => false,
        ],

        'multiline_promoted_properties' => [
            'minimum_number_of_parameters' => 1,
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],

        'ordered_class_elements' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
