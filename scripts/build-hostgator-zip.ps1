# Gera unio-deploy.zip pronto para upload na HostGator.
# Uso: powershell -ExecutionPolicy Bypass -File scripts/build-hostgator-zip.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Unio - gerar unio-deploy.zip ==" -ForegroundColor Cyan

Write-Host "`n[1/4] composer install --no-dev --optimize-autoloader"
& composer install --no-dev --optimize-autoloader
if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`n[2/4] Verificacoes"
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
foreach ($path in $checks) {
    if (-not (Test-Path $path)) {
        Write-Host "  FALTA  $path" -ForegroundColor Red
        Write-Host "Rode antes: powershell -File scripts\prepare-hostgator-upload.ps1" -ForegroundColor Yellow
        exit 1
    }
    Write-Host "  OK  $path" -ForegroundColor Green
}

Write-Host "`n[3/4] Montar pacote (staging)"
$staging = Join-Path $env:TEMP ("unio-deploy-staging-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $staging -Force | Out-Null

$dirs = @("assets", "bin", "config", "migrations", "public", "src", "templates", "vendor")
foreach ($dir in $dirs) {
    Write-Host "  copiando $dir/"
    Copy-Item -Path $dir -Destination (Join-Path $staging $dir) -Recurse -Force
}

$files = @("composer.json", "composer.lock", "symfony.lock", "importmap.php")
foreach ($file in $files) {
    Copy-Item -Path $file -Destination (Join-Path $staging $file) -Force
}

New-Item -ItemType Directory -Path (Join-Path $staging "var\sessions") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $staging "var\cache") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $staging "var\log") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $staging "public\uploads\users") -Force | Out-Null

# Placeholders para pastas vazias no ZIP
foreach ($keep in @("var\cache\.gitkeep", "var\log\.gitkeep", "var\sessions\.gitkeep", "public\uploads\users\.gitkeep")) {
    $keepPath = Join-Path $staging $keep
    if (-not (Test-Path $keepPath)) {
        New-Item -ItemType File -Path $keepPath -Force | Out-Null
    }
}

Write-Host "`n[4/4] Compactar"
$zipPath = Join-Path $root "unio-deploy.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Push-Location $staging
try {
    tar -a -c -f $zipPath *
    if ($LASTEXITCODE -ne 0) {
        throw "tar falhou com codigo $LASTEXITCODE"
    }
}
finally {
    Pop-Location
    Remove-Item -Recurse -Force $staging
}

$sizeMb = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host ""
Write-Host "ZIP criado: $zipPath ($sizeMb MB)" -ForegroundColor Green
Write-Host ""
Write-Host "Proximo passo no cPanel:" -ForegroundColor Cyan
Write-Host "  1. Gerenciador de arquivos -> /home/joabef36/unio/"
Write-Host "  2. Upload de unio-deploy.zip"
Write-Host "  3. Extrair o ZIP nessa pasta"
Write-Host "  4. Document root: /home/joabef36/unio/public"
