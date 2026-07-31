# Deploy JurisFlow AI -> Azure Container Apps
# Uso:
#   1) az login   (ou este script faz login com device code)
#   2) Copie azure-deploy.local.env.example -> azure-deploy.local.env e preencha
#   3) powershell -ExecutionPolicy Bypass -File scripts\azure\deploy-jurisflow.ps1
#
# Requisitos: Docker, Azure CLI (scoop install azure-cli), assinatura Azure ativa

param(
    [switch]$SkipBuild,
    [switch]$SkipLogin,
    [string]$ConfigFile = ""
)

$ErrorActionPreference = "Stop"

function Get-AzCli {
    $scoopAz = Join-Path $env:USERPROFILE "scoop\apps\azure-cli\current\bin\az.cmd"
    if (Test-Path $scoopAz) { return $scoopAz }
    $cmd = Get-Command az -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    throw "Azure CLI nao encontrado. Rode: scoop install azure-cli"
}

function Invoke-Az {
    param([string[]]$CmdArgs)
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    & $script:az @CmdArgs
    $code = $LASTEXITCODE
    $ErrorActionPreference = $prev
    if ($code -ne 0) {
        throw "az $($CmdArgs -join ' ') falhou (exit $code)"
    }
}

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
if ($ConfigFile -eq "") {
    $ConfigFile = Join-Path $root "azure\azure-deploy.local.env"
}

if (-not (Test-Path $ConfigFile)) {
    Write-Host "ERRO: crie $ConfigFile a partir de azure-deploy.local.env.example" -ForegroundColor Red
    exit 1
}

$cfg = @{}
Get-Content $ConfigFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    $idx = $line.IndexOf("=")
    if ($idx -lt 1) { return }
    $cfg[$line.Substring(0, $idx).Trim()] = $line.Substring($idx + 1).Trim()
}

$az = Get-AzCli
$jurisflowDir = $cfg["JURISFLOW_DIR"]
$rg = $cfg["AZURE_RESOURCE_GROUP"]
$loc = $cfg["AZURE_LOCATION"]
$acrName = $cfg["AZURE_ACR_NAME"]
$envName = $cfg["AZURE_CONTAINERAPPS_ENV"]
$appName = $cfg["AZURE_CONTAINERAPP_NAME"]
$imageTag = "v4"

if (-not (Test-Path $jurisflowDir)) {
    throw "JURISFLOW_DIR nao encontrado: $jurisflowDir"
}

foreach ($k in @("AZURE_RESOURCE_GROUP","AZURE_LOCATION","AZURE_ACR_NAME","AZURE_CONTAINERAPPS_ENV","AZURE_CONTAINERAPP_NAME","AZURE_OPENAI_KEY","AZURE_OPENAI_ENDPOINT","AZURE_DEPLOYMENT_NAME","LEGAL_API_SECRET")) {
    if (-not $cfg.ContainsKey($k) -or [string]::IsNullOrWhiteSpace($cfg[$k])) {
        throw "Preencha $k em $ConfigFile"
    }
}

Write-Host "== JurisFlow -> Azure Container Apps ==" -ForegroundColor Cyan

if (-not $SkipLogin) {
    Write-Host "Login Azure (device code se necessario)..." -ForegroundColor Yellow
    & $az login --use-device-code | Out-Null
}

if ($cfg["AZURE_SUBSCRIPTION_ID"]) {
    Invoke-Az -CmdArgs @("account", "set", "--subscription", $cfg["AZURE_SUBSCRIPTION_ID"])
}
$sub = (Invoke-Az -CmdArgs @("account", "show", "--query", "id", "-o", "tsv")).Trim()
Write-Host "Subscription: $sub"

Write-Host "Instalando extensao containerapp (se necessario)..." -ForegroundColor DarkGray
Invoke-Az -CmdArgs @("extension", "add", "--name", "containerapp", "--upgrade", "--only-show-errors") 2>$null

Write-Host "Criando resource group $rg em $loc..." -ForegroundColor Yellow
Invoke-Az -CmdArgs @("group", "create", "--name", $rg, "--location", $loc, "--output", "none")

Write-Host "Criando ACR $acrName..." -ForegroundColor Yellow
$prev = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $az acr create --resource-group $rg --name $acrName --sku Basic --admin-enabled true --output none 2>$null
$ErrorActionPreference = $prev
if ($LASTEXITCODE -ne 0) {
    Write-Host "ACR ja existe ou erro — continuando..." -ForegroundColor DarkGray
}

$acrLoginServer = (Invoke-Az -CmdArgs @("acr", "show", "--name", $acrName, "--query", "loginServer", "-o", "tsv")).Trim()
$acrUser = (Invoke-Az -CmdArgs @("acr", "credential", "show", "--name", $acrName, "--query", "username", "-o", "tsv")).Trim()
$acrPass = (Invoke-Az -CmdArgs @("acr", "credential", "show", "--name", $acrName, "--query", "passwords[0].value", "-o", "tsv")).Trim()

$imageName = "$acrLoginServer/jurisflow-ai:$imageTag"

if (-not $SkipBuild) {
    Write-Host "Build Docker: $jurisflowDir" -ForegroundColor Yellow
    Push-Location $jurisflowDir
    try {
        docker build -t $imageName .
        if ($LASTEXITCODE -ne 0) { throw "docker build falhou" }
    } finally {
        Pop-Location
    }

    Write-Host "Login ACR e push..." -ForegroundColor Yellow
    docker login $acrLoginServer -u $acrUser -p $acrPass
    if ($LASTEXITCODE -ne 0) { throw "docker login ACR falhou" }
    docker push $imageName
    if ($LASTEXITCODE -ne 0) { throw "docker push falhou" }
} else {
    Write-Host "[skip] docker build/push" -ForegroundColor DarkGray
}

Write-Host "Criando Container Apps environment..." -ForegroundColor Yellow
$prev = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $az containerapp env show --name $envName --resource-group $rg --output none 2>$null
$envExists = ($LASTEXITCODE -eq 0)
$ErrorActionPreference = $prev
if (-not $envExists) {
    Invoke-Az -CmdArgs @("containerapp", "env", "create", "--name", $envName, "--resource-group", $rg, "--location", $loc, "--output", "none")
} else {
    Write-Host "Environment $envName ja existe — continuando..." -ForegroundColor DarkGray
}

$llmProvider = if ($cfg["LLM_PROVIDER"]) { $cfg["LLM_PROVIDER"] } else { "azure" }
$symfonyUrl = if ($cfg["SYMFONY_BASE_URL"]) { $cfg["SYMFONY_BASE_URL"] } else { "https://uniojuridico.uniowork.com.br" }

$envVars = @(
    "AI_VERTICAL=legal",
    "LLM_PROVIDER=$llmProvider",
    "AZURE_OPENAI_ENDPOINT=$($cfg['AZURE_OPENAI_ENDPOINT'])",
    "AZURE_DEPLOYMENT_NAME=$($cfg['AZURE_DEPLOYMENT_NAME'])",
    "AGENT_ENABLED=true",
    "RETRIEVAL_METHOD=langchain",
    "LEGAL_API_SECRET=$($cfg['LEGAL_API_SECRET'])",
    "LEGAL_API_URL=$symfonyUrl/api/v1/interno",
    "SYMFONY_BASE_URL=$symfonyUrl"
)

$secretArgs = @(
    "azure-openai-key=$($cfg['AZURE_OPENAI_KEY'])"
)

Write-Host "Criando/atualizando Container App $appName..." -ForegroundColor Yellow
$prev = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $az containerapp show --name $appName --resource-group $rg --output none 2>$null
$appExists = ($LASTEXITCODE -eq 0)
$ErrorActionPreference = $prev
$allEnvVars = $envVars + @("AZURE_OPENAI_KEY=secretref:azure-openai-key")

if (-not $appExists) {
    $createArgs = @(
        "containerapp", "create",
        "--name", $appName,
        "--resource-group", $rg,
        "--environment", $envName,
        "--image", $imageName,
        "--registry-server", $acrLoginServer,
        "--registry-username", $acrUser,
        "--registry-password", $acrPass,
        "--target-port", "8090",
        "--ingress", "external",
        "--min-replicas", "1",
        "--max-replicas", "2",
        "--cpu", "1.0",
        "--memory", "2.0Gi",
        "--secrets", $secretArgs,
        "--env-vars"
    ) + $allEnvVars + @("--output", "none")
    Invoke-Az -CmdArgs $createArgs
} else {
    $updateArgs = @(
        "containerapp", "update",
        "--name", $appName,
        "--resource-group", $rg,
        "--image", $imageName,
        "--set-env-vars"
    ) + $allEnvVars + @("--output", "none")
    Invoke-Az -CmdArgs $updateArgs
}

$fqdn = (Invoke-Az -CmdArgs @("containerapp", "show", "--name", $appName, "--resource-group", $rg, "--query", "properties.configuration.ingress.fqdn", "-o", "tsv")).Trim()
$url = "https://$fqdn"

Write-Host ""
Write-Host "== Deploy concluido ==" -ForegroundColor Green
Write-Host "URL: $url"
Write-Host "Health: $url/health"
Write-Host ""
Write-Host "Proximo passo na HostGator (.env.local do Symfony):" -ForegroundColor Yellow
Write-Host "  LEGAL_AI_URL=$url"
Write-Host "  LEGAL_AI_INTERNAL_SECRET=$($cfg['LEGAL_API_SECRET'])"
Write-Host ""
Write-Host "Teste rapido:" -ForegroundColor Yellow
Write-Host "  curl $url/health"
