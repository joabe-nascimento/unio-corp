# Prepara o projeto local para upload FTP na HostGator (Fase 4).
# Uso: powershell -ExecutionPolicy Bypass -File scripts/prepare-hostgator-upload.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Unio - preparar pacote HostGator ==" -ForegroundColor Cyan

Write-Host "`n[1/3] composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader

Write-Host "`n[2/3] npm ci + vendor:sync"
npm ci
npm run vendor:sync

Write-Host "`n[3/3] Verificacoes"
$checks = @(
    "public\index.php",
    "public\.htaccess",
    "vendor\autoload.php",
    "public\vendor\bootstrap\bootstrap.min.css",
    "assets\app.js",
    "importmap.php",
    "migrations",
    "bin\console"
)
$ok = $true
foreach ($path in $checks) {
    if (Test-Path $path) {
        Write-Host "  OK  $path" -ForegroundColor Green
    } else {
        Write-Host "  FALTA  $path" -ForegroundColor Red
        $ok = $false
    }
}

Write-Host "`n== Enviar via FTP para /home/joabef36/unio/ ==" -ForegroundColor Cyan
Write-Host 'INCLUIR: assets importmap.php bin config migrations public src templates vendor composer.json symfony.lock'
Write-Host 'NAO ENVIAR: node_modules .env.local var/cache var/log .git .cursor'
Write-Host 'Document root: /home/joabef36/unio/public'

if (-not $ok) {
    Write-Host "Corrija os itens FALTA antes do upload." -ForegroundColor Red
    exit 1
}

Write-Host "Pacote pronto para upload." -ForegroundColor Green
Write-Host ""
Write-Host "Gerar ZIP: powershell -ExecutionPolicy Bypass -File scripts\build-hostgator-zip.ps1" -ForegroundColor Cyan
