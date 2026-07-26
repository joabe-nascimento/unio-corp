# Provisionamento completo Unio Jurídico — PC -> HostGator (subdominio + DB + deploy).
#
# Uso (na raiz do repo, branch uniojuridico):
#   powershell -ExecutionPolicy Bypass -File scripts\provision-uniojuridico.ps1
#
# Faz tudo automaticamente:
#   1. Cria config/deploy-uniojuridico.local.env (se ausente, copia do uniosaude)
#   2. SSH: subdominio + banco + .env.local no servidor
#   3. Deploy manual (codigo + migrations + cache)

param(
    [switch]$SkipDeploy,
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"

function Load-EnvFile {
    param([string]$Path, [hashtable]$Into)
    if (-not (Test-Path $Path)) { return }
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq "" -or $line.StartsWith("#")) { return }
        $idx = $line.IndexOf("=")
        if ($idx -lt 1) { return }
        $key = $line.Substring(0, $idx).Trim()
        $val = $line.Substring($idx + 1).Trim()
        $Into[$key] = $val
    }
}

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Provisionamento Unio Juridico (subdominio + DB + deploy) ==" -ForegroundColor Cyan

$juridicoEnv = "$root\config\deploy-uniojuridico.local.env"
$saudeEnv = "$root\config\deploy-uniosaude.local.env"
$exampleEnv = "$root\config\deploy-uniojuridico.local.env.example"

if (-not (Test-Path $juridicoEnv)) {
    if (Test-Path $saudeEnv) {
        Write-Host "[setup] Criando deploy-uniojuridico.local.env a partir do uniosaude..." -ForegroundColor DarkGray
        $content = Get-Content $saudeEnv -Raw
        $content = $content -replace 'uniosaude', 'uniojuridico'
        $content = $content -replace 'Unio Saude', 'Unio Juridico'
        Set-Content -Path $juridicoEnv -Value $content -NoNewline
    } elseif (Test-Path $exampleEnv) {
        Copy-Item $exampleEnv $juridicoEnv
        Write-Host "[setup] Copiado de deploy-uniojuridico.local.env.example — ajuste DEPLOY_KEY_FILE se necessario." -ForegroundColor Yellow
    } else {
        throw "Nao foi possivel criar config/deploy-uniojuridico.local.env"
    }
}

$cfg = @{}
Load-EnvFile "$root\config\deploy-hostgator.defaults.env" $cfg
Load-EnvFile $juridicoEnv $cfg

$sshHost = if ($cfg["DEPLOY_SSH_CANONICAL_HOST"]) { $cfg["DEPLOY_SSH_CANONICAL_HOST"] } else { "br1136.hostgator.com.br" }
$sshUser = if ($cfg["DEPLOY_SSH_DEFAULT_USER"]) { $cfg["DEPLOY_SSH_DEFAULT_USER"] } else { "joabef36" }
$sshPort = if ($cfg["DEPLOY_SSH_DEFAULT_PORT"]) { $cfg["DEPLOY_SSH_DEFAULT_PORT"] } else { "2222" }
$keyFile = if ($cfg["DEPLOY_KEY_FILE"]) { $cfg["DEPLOY_KEY_FILE"] } else { "$env:USERPROFILE\.ssh\unio_deploy" }
$deployPath = if ($cfg["DEPLOY_PATH"]) { $cfg["DEPLOY_PATH"] } else { "/home2/joabef36/unio-uniojuridico" }
$publicHtml = if ($cfg["DEPLOY_PUBLIC_HTML"]) { $cfg["DEPLOY_PUBLIC_HTML"] } else { "/home2/joabef36/uniojuridico.uniowork.com.br" }
$defaultUri = if ($cfg["DEFAULT_URI"]) { $cfg["DEFAULT_URI"] } else { "https://uniojuridico.uniowork.com.br" }

if (-not (Test-Path $keyFile)) {
    throw "Chave SSH nao encontrada: $keyFile"
}

$sshBase = @("-p", $sshPort, "-i", $keyFile, "-o", "StrictHostKeyChecking=accept-new", "-o", "BatchMode=yes", "-o", "AddressFamily=inet", "-o", "ConnectTimeout=20")
$sshTarget = "${sshUser}@${sshHost}"

Write-Host "[1/3] SSH preflight..." -ForegroundColor Cyan
& ssh @sshBase $sshTarget "echo provision-ssh-ok" | Out-Null
if ($LASTEXITCODE -ne 0) { throw "SSH preflight falhou" }
Write-Host "  OK" -ForegroundColor Green

Write-Host "[2/3] Subdominio + banco + .env.local no servidor..." -ForegroundColor Cyan
$setupScript = "$root\scripts\setup-uniojuridico-hostgator.sh"
& scp -P $sshPort -i $keyFile -o StrictHostKeyChecking=accept-new -o BatchMode=yes $setupScript "${sshTarget}:/tmp/setup-uniojuridico-hostgator.sh"
if ($LASTEXITCODE -ne 0) { throw "scp setup script falhou" }

& ssh @sshBase $sshTarget "sed -i 's/\r$//' /tmp/setup-uniojuridico-hostgator.sh && chmod +x /tmp/setup-uniojuridico-hostgator.sh && DEPLOY_PATH='$deployPath' PUBLIC_HTML='$publicHtml' bash /tmp/setup-uniojuridico-hostgator.sh"
if ($LASTEXITCODE -ne 0) { throw "setup no servidor falhou" }
Write-Host "  OK" -ForegroundColor Green

if (-not $SkipDeploy) {
    Write-Host "[3/3] Deploy do codigo..." -ForegroundColor Cyan
    $deployArgs = @()
    if ($SkipBuild) { $deployArgs += "-SkipBuild" }
    & powershell -ExecutionPolicy Bypass -File "$root\scripts\deploy-uniojuridico-manual.ps1" @deployArgs
    if ($LASTEXITCODE -ne 0) { throw "deploy falhou" }

    Write-Host ""
    Write-Host "[repair] Sincronizando public_html..." -ForegroundColor Cyan
    & ssh @sshBase $sshTarget "export DEPLOY_PATH='$deployPath' PUBLIC_HTML='$publicHtml' DEFAULT_URI='$defaultUri' && bash '$deployPath/scripts/repair-uniojuridico-now.sh'"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  AVISO: repair retornou codigo $LASTEXITCODE (pode ser normal no primeiro deploy)" -ForegroundColor Yellow
    }
} else {
    Write-Host "[3/3] Deploy ignorado (--SkipDeploy)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Provisionamento concluido." -ForegroundColor Green
Write-Host "URL: $defaultUri/login" -ForegroundColor Green
Write-Host "Login inicial: admin@uniowork.com.br / unio123 (altere apos primeiro acesso)" -ForegroundColor DarkGray

try {
    $resp = Invoke-WebRequest -Uri "$defaultUri/login" -Method Head -UseBasicParsing -TimeoutSec 30
    Write-Host "[smoke] HTTP $($resp.StatusCode)" -ForegroundColor Green
} catch {
    if ($_.Exception.Response) {
        $code = [int]$_.Exception.Response.StatusCode
        Write-Host "[smoke] HTTP $code — site respondeu (SSL pode levar alguns minutos para estabilizar)" -ForegroundColor Yellow
    } else {
        Write-Host "[smoke] $($_.Exception.Message) — aguarde DNS/SSL se acabou de criar o subdominio" -ForegroundColor Yellow
    }
}
