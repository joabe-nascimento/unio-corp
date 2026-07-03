# Gera unio-public-assets.zip para extrair em public_html na HostGator.
# Uso: powershell -ExecutionPolicy Bypass -File scripts/build-public-assets-zip.ps1

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Unio - gerar unio-public-assets.zip ==" -ForegroundColor Cyan

$dirs = @("css", "js", "images", "vendor", "uploads")
$missing = @()
foreach ($dir in $dirs) {
    if (-not (Test-Path "public\$dir")) {
        $missing += $dir
    }
}
if ($missing.Count -gt 0) {
    Write-Host "FALTA em public/: $($missing -join ', ')" -ForegroundColor Red
    Write-Host "Rode: npm run vendor:sync" -ForegroundColor Yellow
    exit 1
}

$staging = Join-Path $env:TEMP ("unio-public-staging-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $staging -Force | Out-Null

foreach ($dir in $dirs) {
    Write-Host "  copiando public/$dir/"
    Copy-Item -Path "public\$dir" -Destination (Join-Path $staging $dir) -Recurse -Force
}

$extraFiles = @("favicon.ico", "robots.txt")
foreach ($file in $extraFiles) {
    if (Test-Path "public\$file") {
        Copy-Item -Path "public\$file" -Destination (Join-Path $staging $file) -Force
    }
}

$zipPath = Join-Path $root "unio-public-assets.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Push-Location $staging
try {
    tar -a -c -f $zipPath *
    if ($null -ne $LASTEXITCODE -and $LASTEXITCODE -ne 0) {
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
Write-Host "Destino no servidor: /home2/joabef36/public_html/" -ForegroundColor Cyan
Write-Host "  1. Gerenciador de arquivos -> public_html"
Write-Host "  2. Upload unio-public-assets.zip"
Write-Host "  3. Extrair DENTRO de public_html (nao criar subpasta)"
Write-Host "  4. Deve ficar: public_html/css, public_html/js, public_html/images, ..."
Write-Host "  5. Apagar o .zip depois"
