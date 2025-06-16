<?php
$header = <<<EOF
This file is part of {{name}}

{{description}}
EOF;

$info = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);

$header = trim(str_replace(
    ['{{name}}', '{{description}}' ],
    [$info['name'], $info['description'] ?? null],
    $header
));


$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests'
    ]);

return (new PhpCsFixer\Config())
->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
->setFinder($finder)
->setRules([
    '@PER-CS' => true,

    'header_comment' => [
        'comment_type' => 'PHPDoc',
        'header' => $header,
        'location' => 'after_open',
        'separate' => 'both',
    ]
]);
