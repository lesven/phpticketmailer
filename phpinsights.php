<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Paths to Analyse
    |--------------------------------------------------------------------------
    | The paths to analyze in PHPInsights.
    */
    'paths' => [
        'src',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude Paths
    |--------------------------------------------------------------------------
    | Paths to exclude from analysis.
    */
    'exclude' => [
        'vendor',
        'node_modules',
        'build',
        'var',
        'tests',
        'migrations',
        'public',
        'templates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Preset
    |--------------------------------------------------------------------------
    | Use 'symfony' preset for Symfony projects.
    */
    'preset' => 'symfony',
];

