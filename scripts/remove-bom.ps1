#!/usr/bin/env pwsh
# Remove BOM (Byte Order Mark) de arquivos críticos para evitar erros no deploy

param(
    [switch]$CheckOnly = $false,
    [switch]$Verbose = $false
)

$utf8NoBom = New-Object System.Text.UTF8Encoding $false
$filesChecked = 0
$filesFixed = 0
$filesWithBom = @()

# Padrões de arquivos críticos que não devem ter BOM
$patterns = @(
    "*.env",
    "*.php",
    "*.yaml",
    "*.yml",
    "*.twig",
    "*.js",
    "*.json",
    "*.xml",
    "*.md",
    "*.sh"
)

# Diretórios a verificar
$directories = @(
    ".",
    "src",
    "config",
    "templates",
    "public",
    "tests"
)

Write-Host "==> Remove BOM de arquivos críticos" -ForegroundColor Cyan
Write-Host ""

if ($CheckOnly) {
    Write-Host "Modo: VERIFICAÇÃO (sem modificar arquivos)" -ForegroundColor Yellow
} else {
    Write-Host "Modo: CORREÇÃO (remove BOM encontrado)" -ForegroundColor Green
}
Write-Host ""

function Test-FileHasBom {
    param([string]$FilePath)
    
    $bytes = [System.IO.File]::ReadAllBytes($FilePath)
    if ($bytes.Length -lt 3) { return $false }
    
    # UTF-8 BOM: EF BB BF
    return ($bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF)
}

function Remove-BomFromFile {
    param([string]$FilePath)
    
    try {
        $content = Get-Content $FilePath -Raw
        [System.IO.File]::WriteAllText($FilePath, $content, $utf8NoBom)
        return $true
    }
    catch {
        Write-Host "  ❌ Erro ao processar: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

foreach ($dir in $directories) {
    if (-not (Test-Path $dir)) { continue }
    
    foreach ($pattern in $patterns) {
        $files = Get-ChildItem -Path $dir -Filter $pattern -File -Recurse -ErrorAction SilentlyContinue
        
        foreach ($file in $files) {
            $filesChecked++
            
            if (Test-FileHasBom -FilePath $file.FullName) {
                $filesWithBom += $file.FullName
                $relativePath = $file.FullName.Replace((Get-Location).Path, ".").Replace("\", "/")
                
                if ($CheckOnly) {
                    Write-Host "  🔍 BOM encontrado: $relativePath" -ForegroundColor Yellow
                } else {
                    if (Remove-BomFromFile -FilePath $file.FullName) {
                        Write-Host "  ✅ BOM removido: $relativePath" -ForegroundColor Green
                        $filesFixed++
                    }
                }
            } elseif ($Verbose) {
                $relativePath = $file.FullName.Replace((Get-Location).Path, ".").Replace("\", "/")
                Write-Host "  ✓ OK: $relativePath" -ForegroundColor DarkGray
            }
        }
    }
}

Write-Host ""
Write-Host "==> Resumo" -ForegroundColor Cyan
Write-Host "Arquivos verificados: $filesChecked"
Write-Host "Arquivos com BOM: $($filesWithBom.Count)"

if ($CheckOnly) {
    if ($filesWithBom.Count -gt 0) {
        Write-Host ""
        Write-Host "Execute sem -CheckOnly para remover o BOM automaticamente." -ForegroundColor Yellow
        exit 1
    } else {
        Write-Host "✅ Nenhum BOM encontrado!" -ForegroundColor Green
        exit 0
    }
} else {
    Write-Host "Arquivos corrigidos: $filesFixed"
    
    if ($filesFixed -gt 0) {
        Write-Host ""
        Write-Host "✅ BOM removido com sucesso de $filesFixed arquivo(s)!" -ForegroundColor Green
        Write-Host "   Lembre-se de commitar as mudanças." -ForegroundColor Yellow
    } else {
        Write-Host "✅ Nenhuma correção necessária!" -ForegroundColor Green
    }
    exit 0
}
