<#
.SYNOPSIS
    Importa dados históricos de uso de tokens da Azure OpenAI para o Unio Jurídico.

.DESCRIPTION
    Este script automatiza o processo de:
    1. Login na Azure CLI
    2. Listagem de recursos Azure OpenAI
    3. Obtenção de token de acesso
    4. Execução do comando Symfony de importação

.PARAMETER ResourceId
    Resource ID do Azure OpenAI (opcional - será solicitado interativamente)

.PARAMETER Days
    Número de dias históricos para importar (padrão: 30)

.PARAMETER DryRun
    Modo dry-run - apenas visualiza sem salvar

.EXAMPLE
    .\import-azure-token-history.ps1
    Executa interativamente, perguntando o Resource ID

.EXAMPLE
    .\import-azure-token-history.ps1 -Days 90
    Importa os últimos 90 dias

.EXAMPLE
    .\import-azure-token-history.ps1 -DryRun
    Apenas visualiza os dados sem salvar
#>

param(
    [string]$ResourceId = $null,
    [int]$Days = 30,
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host " Importação de Tokens Históricos - Azure OpenAI → Unio " -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# Verificar Azure CLI
Write-Host "[1/5] Verificando Azure CLI..." -ForegroundColor Yellow
try {
    $azVersion = az version --query '\"azure-cli\"' -o tsv 2>$null
    Write-Host "✓ Azure CLI instalado: v$azVersion" -ForegroundColor Green
} catch {
    Write-Host "✗ Azure CLI não encontrado!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Instale a Azure CLI:" -ForegroundColor White
    Write-Host "  https://docs.microsoft.com/cli/azure/install-azure-cli" -ForegroundColor White
    exit 1
}

# Login na Azure
Write-Host ""
Write-Host "[2/5] Login na Azure..." -ForegroundColor Yellow
try {
    $account = az account show 2>$null | ConvertFrom-Json
    Write-Host "✓ Logado como: $($account.user.name)" -ForegroundColor Green
    Write-Host "  Subscription: $($account.name)" -ForegroundColor Gray
} catch {
    Write-Host "⚠ Não está logado. Abrindo login da Azure..." -ForegroundColor Yellow
    az login
    $account = az account show | ConvertFrom-Json
    Write-Host "✓ Login realizado: $($account.user.name)" -ForegroundColor Green
}

# Listar recursos OpenAI
Write-Host ""
Write-Host "[3/5] Listando recursos Azure OpenAI..." -ForegroundColor Yellow
$resources = az cognitiveservices account list --query "[?kind=='OpenAI'].{name:name, resourceGroup:resourceGroup, id:id, location:location}" | ConvertFrom-Json

if ($resources.Count -eq 0) {
    Write-Host "✗ Nenhum recurso Azure OpenAI encontrado nesta subscription!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Verifique se:" -ForegroundColor White
    Write-Host "  1. Você está na subscription correta: az account set -s <subscription-id>" -ForegroundColor Gray
    Write-Host "  2. Você tem permissões para listar recursos" -ForegroundColor Gray
    Write-Host "  3. O recurso Azure OpenAI está criado" -ForegroundColor Gray
    exit 1
}

Write-Host "✓ Encontrado(s) $($resources.Count) recurso(s) Azure OpenAI:" -ForegroundColor Green
Write-Host ""

for ($i = 0; $i -lt $resources.Count; $i++) {
    $r = $resources[$i]
    Write-Host "  [$($i + 1)] $($r.name)" -ForegroundColor Cyan
    Write-Host "      Resource Group: $($r.resourceGroup)" -ForegroundColor Gray
    Write-Host "      Location: $($r.location)" -ForegroundColor Gray
    Write-Host ""
}

# Selecionar recurso
if ([string]::IsNullOrEmpty($ResourceId)) {
    if ($resources.Count -eq 1) {
        $ResourceId = $resources[0].id
        Write-Host "✓ Selecionado automaticamente: $($resources[0].name)" -ForegroundColor Green
    } else {
        $selection = Read-Host "Escolha o número do recurso [1-$($resources.Count)]"
        $index = [int]$selection - 1
        if ($index -ge 0 -and $index -lt $resources.Count) {
            $ResourceId = $resources[$index].id
            Write-Host "✓ Selecionado: $($resources[$index].name)" -ForegroundColor Green
        } else {
            Write-Host "✗ Seleção inválida!" -ForegroundColor Red
            exit 1
        }
    }
}

# Obter token de acesso
Write-Host ""
Write-Host "[4/5] Obtendo token de acesso do Azure Resource Manager..." -ForegroundColor Yellow
try {
    $token = az account get-access-token --query accessToken -o tsv
    Write-Host "✓ Token obtido (válido por 1 hora)" -ForegroundColor Green
} catch {
    Write-Host "✗ Erro ao obter token!" -ForegroundColor Red
    exit 1
}

# Executar importação
Write-Host ""
Write-Host "[5/5] Executando importação de dados históricos..." -ForegroundColor Yellow
Write-Host "  Período: últimos $Days dias" -ForegroundColor Gray
Write-Host "  Modo: $(if ($DryRun) { 'DRY RUN (não vai salvar)' } else { 'PRODUÇÃO' })" -ForegroundColor Gray
Write-Host ""

$phpCmd = "php"
$consolePath = "bin/console"

$args = @(
    $consolePath,
    "app:import-azure-token-usage",
    "--resource-id=`"$ResourceId`"",
    "--management-token=`"$token`"",
    "--days=$Days"
)

if ($DryRun) {
    $args += "--dry-run"
}

$cmd = "$phpCmd " + ($args -join " ")

try {
    Invoke-Expression $cmd
    $exitCode = $LASTEXITCODE
    
    Write-Host ""
    if ($exitCode -eq 0) {
        Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Green
        Write-Host " ✓ Importação concluída com sucesso!" -ForegroundColor Green
        Write-Host "═══════════════════════════════════════════════════════" -ForegroundColor Green
        Write-Host ""
        Write-Host "Próximos passos:" -ForegroundColor White
        Write-Host "  1. Acesse: https://uniojuridico.uniowork.com.br" -ForegroundColor Gray
        Write-Host "  2. Faça login como joabe.nascimento@unio.dev" -ForegroundColor Gray
        Write-Host "  3. Veja o medidor de tokens no canto superior direito" -ForegroundColor Gray
    } else {
        Write-Host "✗ Importação falhou com código de saída: $exitCode" -ForegroundColor Red
    }
} catch {
    Write-Host "✗ Erro ao executar o comando!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}
