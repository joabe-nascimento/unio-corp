# Instala hook pre-push no Windows.
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$HooksDir = Join-Path $Root ".git\hooks"
$Source = Join-Path $Root "scripts\hooks\pre-push"
$Target = Join-Path $HooksDir "pre-push"

if (-not (Test-Path (Join-Path $Root ".git"))) {
    Write-Error "Repositório git não encontrado."
}

New-Item -ItemType Directory -Force -Path $HooksDir | Out-Null
Copy-Item -Force $Source $Target

Write-Host "Hook pre-push instalado em .git/hooks/pre-push"
Write-Host "Instale Git Bash para o hook funcionar, ou rode manualmente:"
Write-Host "  .\scripts\validate-before-push.ps1"
