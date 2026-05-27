<?php

/**
 * Returns the importmap for this application.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'rh_ux' => [
        'path' => './assets/rh_ux.js',
        'entrypoint' => true,
    ],
    'core_projetos' => [
        'path' => './assets/core_projetos.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'path' => './assets/lib/stimulus.js',
        'type' => 'js',
    ],
    '@hotwired/turbo' => [
        'path' => './assets/lib/turbo.js',
        'type' => 'js',
    ],
];
