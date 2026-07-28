#!/usr/bin/env pwsh
# Verifica problemas comuns antes do deploy

param(
    [switch]$Fix = $false
)

$hasErrors = $false

Write-Host ""
Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   PRÉ-DEPLOY CHECK - Unio Jurídico        ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# 1. Verificar BOM em arquivos críticos
Write-Host "[1/4] Verificando BOM em arquivos..." -ForegroundColor Yellow

if ($Fix) {
    & "$PSScriptRoot/remove-bom.ps1"
} else {
    & "$PSScriptRoot/remove-bom.ps1" -CheckOnly
}

if ($LASTEXITCODE -ne 0) {
    $hasErrors = $true
    Write-Host "      ❌ BOM encontrado em arquivos" -ForegroundColor Red
    Write-Host "      Execute com -Fix para corrigir automaticamente" -ForegroundColor Yellow
} else {
    Write-Host "      ✅ Nenhum BOM encontrado" -ForegroundColor Green
}

Write-Host ""

# 2. Verificar arquivos com espaços ou caracteres especiais no nome
Write-Host "[2/4] Verificando nomes de arquivos..." -ForegroundColor Yellow

$badFiles = Get-ChildItem -Path "src","config","templates" -Recurse -File | 
    Where-Object { $_.Name -match '[^\x00-\x7F]' -or $_.Name -match '\s{2,}' }

if ($badFiles) {
    $hasErrors = $true
    Write-Host "      ❌ Arquivos com caracteres problemáticos:" -ForegroundColor Red
    $badFiles | ForEach-Object { 
        Write-Host "         $($_.FullName.Replace((Get-Location).Path, '.'))" -ForegroundColor Red 
    }
} else {
    Write-Host "      ✅ Nomes de arquivos OK" -ForegroundColor Green
}

Write-Host ""

# 3. Verificar PSR-4 compliance (classes renomeadas)
Write-Host "[3/4] Verificando PSR-4 compliance..." -ForegroundColor Yellow

$psr4Errors = @()
$phpFiles = Get-ChildItem -Path "src" -Filter "*.php" -Recurse

foreach ($file in $phpFiles) {
    $content = Get-Content $file.FullName -Raw
    
    # Extrair namespace
    if ($content -match 'namespace\s+([\w\\]+);') {
        $namespace = $matches[1]
        
        # Extrair nome da classe
        if ($content -match '(class|interface|trait)\s+(\w+)') {
            $className = $matches[2]
            $expectedFileName = "$className.php"
            
            if ($file.Name -ne $expectedFileName) {
                $psr4Errors += @{
                    File = $file.FullName.Replace((Get-Location).Path, ".")
                    Expected = $expectedClassName
                    Found = $file.BaseName
                }
            }
        }
    }
}

if ($psr4Errors.Count -gt 0) {
    $hasErrors = $true
    Write-Host "      ❌ Erros de PSR-4 encontrados:" -ForegroundColor Red
    $psr4Errors | ForEach-Object {
        Write-Host "         $($_.File): classe '$($_.Expected)' em arquivo '$($_.Found).php'" -ForegroundColor Red
    }
} else {
    Write-Host "      ✅ PSR-4 compliance OK" -ForegroundColor Green
}

Write-Host ""

# 4. Verificar .env tem todas as variáveis necessárias
Write-Host "[4/4] Verificando .env..." -ForegroundColor Yellow

if (Test-Path ".env") {
    $requiredVars = @(
        "APP_ENV",
        "APP_SECRET",
        "DATABASE_URL",
        "SASHA_AI_ENABLED",
        "LEGAL_AI_ENABLED"
    )
    
    $envContent = Get-Content ".env" -Raw
    $missingVars = @()
    
    foreach ($var in $requiredVars) {
        if ($envContent -notmatch "^$var=") {
            $missingVars += $var
        }
    }
    
    if ($missingVars.Count -gt 0) {
        Write-Host "      ⚠️  Variáveis faltando no .env:" -ForegroundColor Yellow
        $missingVars | ForEach-Object { Write-Host "         - $_" -ForegroundColor Yellow }
    } else {
        Write-Host "      ✅ .env configurado" -ForegroundColor Green
    }
} else {
    Write-Host "      ⚠️  Arquivo .env não encontrado" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "╔════════════════════════════════════════════╗" -ForegroundColor Cyan

if ($hasErrors) {
    Write-Host "║   ❌ PRÉ-DEPLOY CHECK FALHOU              ║" -ForegroundColor Red
    Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Corrija os erros acima antes de fazer deploy." -ForegroundColor Red
    if (-not $Fix) {
        Write-Host "Use -Fix para tentar corrigir automaticamente." -ForegroundColor Yellow
    }
    exit 1
} else {
    Write-Host "║   ✅ PRÉ-DEPLOY CHECK PASSOU               ║" -ForegroundColor Green
    Write-Host "╚════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Projeto pronto para deploy! 🚀" -ForegroundColor Green
    exit 0
}
