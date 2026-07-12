<?php

declare(strict_types=1);

/**
 * Atualiza taglines legadas em var/admin_config.json (ex.: "Cuidado que continua.").
 * Uso: php scripts/lib/migrate-legacy-branding.php /path/to/project
 */

$projectDir = $argv[1] ?? getcwd();
$configPath = rtrim($projectDir, '/\\') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'admin_config.json';

if (!is_file($configPath)) {
    echo "migrate-legacy-branding: sem admin_config.json — ignorado\n";
    exit(0);
}

$raw = file_get_contents($configPath);
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "migrate-legacy-branding: admin_config.json invalido\n");
    exit(1);
}

$newTagline = resolveBrandSlogan($projectDir);
$legacy = ['Cuidado que continua.', 'Cuidado que continua', ''];
$changed = [];

foreach (['plataforma_tagline', 'rodape_texto'] as $key) {
    if (!isset($data[$key])) {
        continue;
    }
    $val = trim((string) $data[$key]);
    if ($key === 'rodape_texto' && $val === '') {
        continue;
    }
    if ($val === '' || in_array($val, $legacy, true)) {
        $data[$key] = $newTagline;
        $changed[] = $key;
    }
}

if ($changed === []) {
    echo "migrate-legacy-branding: nada a alterar\n";
    exit(0);
}

file_put_contents(
    $configPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
);

echo 'migrate-legacy-branding: atualizado ' . implode(', ', $changed)
    . ' -> ' . $newTagline . "\n";

function resolveBrandSlogan(string $projectDir): string
{
    foreach (['.env.local', '.env'] as $file) {
        $path = $projectDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'UNIO_ORGANISMO_BRAND_SLOGAN=')) {
                continue;
            }
            $value = trim(substr($line, strlen('UNIO_ORGANISMO_BRAND_SLOGAN=')), " \t\"'");
            if ($value !== '') {
                return $value;
            }
        }
    }

    return 'Saúde que acompanha.';
}
