#!/usr/bin/env pwsh
# Instala git hooks personalizados do projeto

$hookSource = ".githooks"
$hookTarget = ".git/hooks"

Write-Host ""
Write-Host "==> Instalando Git Hooks" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path $hookSource)) {
    Write-Host "❌ Diretório $hookSource não encontrado" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $hookTarget)) {
    Write-Host "❌ Não parece ser um repositório git" -ForegroundColor Red
    exit 1
}

$hooks = Get-ChildItem -Path $hookSource -File

$installed = 0
foreach ($hook in $hooks) {
    $targetPath = Join-Path $hookTarget $hook.Name
    
    # Copiar hook
    Copy-Item $hook.FullName $targetPath -Force
    
    # No Windows, criar wrapper .bat se necessário
    if ($IsWindows -or $env:OS -match "Windows") {
        $wrapperContent = @"
@echo off
sh "%~dp0$($hook.Name)" %*
"@
        $wrapperPath = "$targetPath.bat"
        Set-Content -Path $wrapperPath -Value $wrapperContent
    }
    
    Write-Host "  ✅ Instalado: $($hook.Name)" -ForegroundColor Green
    $installed++
}

Write-Host ""
Write-Host "✅ $installed hook(s) instalado(s) com sucesso!" -ForegroundColor Green
Write-Host ""
Write-Host "Os hooks ativos são:" -ForegroundColor Cyan
Get-ChildItem -Path $hookTarget -File | Where-Object { $_.Extension -ne ".sample" } | ForEach-Object {
    Write-Host "  - $($_.Name)" -ForegroundColor White
}
Write-Host ""
