<?php

declare(strict_types=1);

/**
 * Lê DATABASE_URL de .env / .env.local e imprime variáveis para mysqldump.
 * Uso: eval "$(php scripts/lib/print-db-mysql-params.php /path/to/project)"
 */

$projectDir = $argv[1] ?? getcwd();
if (!is_dir($projectDir)) {
    fwrite(STDERR, "Diretório inválido: {$projectDir}\n");
    exit(1);
}

/** @return array<string, string> */
function loadEnvFiles(string $dir): array
{
    $env = [];
    foreach (['.env', '.env.local'] as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\"'");
        }
    }

    return $env;
}

$env = loadEnvFiles($projectDir);
$url = $env['DATABASE_URL'] ?? '';
if ($url === '') {
    fwrite(STDERR, "DATABASE_URL não encontrada em {$projectDir}\n");
    exit(1);
}

$parts = parse_url($url);
if ($parts === false || ($parts['scheme'] ?? '') !== 'mysql') {
    fwrite(STDERR, "DATABASE_URL inválida ou não é mysql\n");
    exit(1);
}

$user = urldecode((string) ($parts['user'] ?? ''));
$pass = urldecode((string) ($parts['pass'] ?? ''));
$host = (string) ($parts['host'] ?? 'localhost');
$port = (string) ($parts['port'] ?? '3306');
$db = ltrim((string) ($parts['path'] ?? ''), '/');

if ($db === '') {
    fwrite(STDERR, "Nome do banco ausente em DATABASE_URL\n");
    exit(1);
}

$emit = static function (string $name, string $value): void {
    echo $name . '=' . escapeshellarg($value) . "\n";
};

$emit('DB_MYSQL_USER', $user);
$emit('DB_MYSQL_PASS', $pass);
$emit('DB_MYSQL_HOST', $host);
$emit('DB_MYSQL_PORT', $port);
$emit('DB_MYSQL_NAME', $db);
