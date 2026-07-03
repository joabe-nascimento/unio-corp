# Gera unio-assets.zip (assets/ + importmap.php) para upload na HostGator.
# Uso: powershell -ExecutionPolicy Bypass -File scripts/build-hostgator-assets-zip.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Unio - gerar unio-assets.zip ==" -ForegroundColor Cyan

if (-not (Test-Path "assets\app.js")) {
    Write-Host "FALTA assets/app.js" -ForegroundColor Red
    exit 1
}
if (-not (Test-Path "importmap.php")) {
    Write-Host "FALTA importmap.php" -ForegroundColor Red
    exit 1
}

$staging = Join-Path $env:TEMP ("unio-assets-staging-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $staging -Force | Out-Null

Write-Host "  copiando assets/"
Copy-Item -Path "assets" -Destination (Join-Path $staging "assets") -Recurse -Force
Copy-Item -Path "importmap.php" -Destination (Join-Path $staging "importmap.php") -Force

$zipPath = Join-Path $root "unio-assets.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Push-Location $staging
try {
    tar -a -c -f $zipPath *
    if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) {
        throw "tar falhou com codigo $LASTEXITCODE"
    }
} finally {
    Pop-Location
    Remove-Item -Recurse -Force $staging
}

$sizeMb = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host ""
Write-Host "ZIP criado: $zipPath ($sizeMb MB)" -ForegroundColor Green
Write-Host ""
Write-Host "Destino no servidor: /home2/joabef36/unio/" -ForegroundColor Cyan
Write-Host "  1. Upload unio-assets.zip"
Write-Host "  2. Extrair DENTRO de unio/ (deve ficar unio/assets/ e unio/importmap.php)"
Write-Host "  3. Limpar cache: acesse fix-once.php ou cache:clear"
