#!/usr/bin/env bash
# Atualiza taglines legadas em var/admin_config.json (ex.: "Cuidado que continua.").
set -euo pipefail

DEPLOY_PATH="${1:-.}"
CFG="$DEPLOY_PATH/var/admin_config.json"
PHP_BIN="${PHP_BIN:-php}"

if [[ ! -f "$CFG" ]]; then
  echo "migrate-legacy-branding: sem admin_config.json — ignorado"
  exit 0
fi

$PHP_BIN -r '
$path = $argv[1];
$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data)) { fwrite(STDERR, "admin_config.json inválido\n"); exit(1); }
$legacy = ["Cuidado que continua.", "Cuidado que continua"];
$new = "Saúde que acompanha.";
$changed = false;
foreach (["plataforma_tagline", "rodape_texto"] as $key) {
    if (!isset($data[$key])) { continue; }
    $val = trim((string) $data[$key]);
    if ($val === "" || in_array($val, $legacy, true)) {
        if ($key === "rodape_texto" && $val === "") { continue; }
        $data[$key] = $new;
        $changed = true;
    }
}
if (!$changed) { echo "migrate-legacy-branding: nada a alterar\n"; exit(0); }
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo "migrate-legacy-branding: tagline atualizada para «$new»\n";
' "$CFG"
