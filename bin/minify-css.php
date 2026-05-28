<?php

/**
 * Minifica public/css/unio-app.css → unio-app.min.css (remove comentários e espaço extra).
 * Uso: php bin/minify-css.php
 */
$root = dirname(__DIR__);
$source = $root . '/public/css/unio-app.css';
$target = $root . '/public/css/unio-app.min.css';

if (!is_readable($source)) {
    fwrite(STDERR, "Arquivo não encontrado: {$source}\n");
    exit(1);
}

$css = file_get_contents($source);
if ($css === false) {
    fwrite(STDERR, "Falha ao ler CSS.\n");
    exit(1);
}

$min = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
$min = preg_replace('/\s+/', ' ', $min) ?? $min;
$min = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $min) ?? $min;
$min = trim($min);

if (file_put_contents($target, $min) === false) {
    fwrite(STDERR, "Falha ao gravar {$target}\n");
    exit(1);
}

$before = filesize($source);
$after = filesize($target);
$pct = $before > 0 ? round(100 - ($after / $before * 100), 1) : 0;

echo sprintf(
    "OK: %s → %s (%.1f KB → %.1f KB, -%s%%)\n",
    basename($source),
    basename($target),
    $before / 1024,
    $after / 1024,
    $pct,
);
