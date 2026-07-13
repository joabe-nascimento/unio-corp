# Deploy manual Unio Saude — PC (Windows) -> HostGator via SSH.
# Espelha o pipeline do GitHub Actions sem depender do Actions.
#
# Uso (na raiz do repo):
#   powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
#
# Pre-requisitos:
#   - OpenSSH (ssh/scp) no PATH
#   - tar (Windows 10+)
#   - composer, npm, php
#   - config/deploy-uniosaude.local.env com DEPLOY_KEY_FILE

param(
    [switch]$SkipBuild,
    [string]$ArchiveName = "deploy-uniosaude.tar.gz"
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

function Invoke-Retry {
    param(
        [scriptblock]$Action,
        [int]$Attempts = 3,
        [int]$DelaySec = 15
    )
    for ($i = 1; $i -le $Attempts; $i++) {
        try {
            & $Action
            return
        } catch {
            if ($i -ge $Attempts) { throw }
            Write-Host "Tentativa $i/$Attempts falhou; aguardando ${DelaySec}s..." -ForegroundColor Yellow
            Start-Sleep -Seconds $DelaySec
        }
    }
}

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Deploy manual Unio Saude (PC -> HostGator) ==" -ForegroundColor Cyan

$cfg = @{}
Load-EnvFile "$root\config\deploy-hostgator.defaults.env" $cfg
$localEnv = "$root\config\deploy-uniosaude.local.env"
if (-not (Test-Path $localEnv)) {
    Write-Host ""
    Write-Host "ERRO: crie config/deploy-uniosaude.local.env" -ForegroundColor Red
    Write-Host "      Copie de config/deploy-uniosaude.local.env.example" -ForegroundColor Red
    Write-Host "      e defina DEPLOY_KEY_FILE com a chave SSH do cPanel." -ForegroundColor Red
    exit 1
}
Load-EnvFile $localEnv $cfg

$sshHost = $cfg["DEPLOY_SSH_CANONICAL_HOST"]
if (-not $sshHost) { $sshHost = "br1136.hostgator.com.br" }
$sshUser = $cfg["DEPLOY_SSH_DEFAULT_USER"]
if (-not $sshUser) { $sshUser = "joabef36" }
$sshPort = $cfg["DEPLOY_SSH_DEFAULT_PORT"]
if (-not $sshPort) { $sshPort = "2222" }
$deployPath = $cfg["DEPLOY_PATH"]
if (-not $deployPath) { $deployPath = "/home2/joabef36/unio-uniosaude" }
$publicHtml = $cfg["DEPLOY_PUBLIC_HTML"]
if (-not $publicHtml) { $publicHtml = "/home2/joabef36/uniosaude.uniowork.com.br" }
$keyFile = $cfg["DEPLOY_KEY_FILE"]
if (-not $keyFile) { $keyFile = "$env:USERPROFILE\.ssh\unio_deploy" }

if (-not (Test-Path $keyFile)) {
    Write-Host "ERRO: chave SSH nao encontrada: $keyFile" -ForegroundColor Red
    exit 1
}

$sshBase = @(
    "-p", $sshPort,
    "-i", $keyFile,
    "-o", "StrictHostKeyChecking=accept-new",
    "-o", "BatchMode=yes",
    "-o", "AddressFamily=inet",
    "-o", "ConnectTimeout=20"
)
$scpBase = @(
    "-P", $sshPort,
    "-i", $keyFile,
    "-o", "StrictHostKeyChecking=accept-new",
    "-o", "BatchMode=yes",
    "-o", "AddressFamily=inet",
    "-o", "ConnectTimeout=20"
)
$sshTarget = "${sshUser}@${sshHost}"

Write-Host "Alvo SSH: $sshTarget (porta $sshPort)" -ForegroundColor DarkGray
Write-Host "App:      $deployPath" -ForegroundColor DarkGray
Write-Host "Public:   $publicHtml" -ForegroundColor DarkGray

Invoke-Retry {
    & ssh @sshBase $sshTarget "echo deploy-ssh-ok" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "SSH preflight falhou (exit $LASTEXITCODE)" }
}
Write-Host "[ok] SSH conectado" -ForegroundColor Green

if (-not $SkipBuild) {
    Write-Host ""
    Write-Host "[1/4] composer install (prod)" -ForegroundColor Cyan
    & composer install --no-dev --no-progress --prefer-dist --optimize-autoloader --no-scripts
    if ($LASTEXITCODE -ne 0) { throw "composer install falhou" }

    Write-Host "[2/4] npm ci + vendor:sync + minify CSS" -ForegroundColor Cyan
    & npm ci
    if ($LASTEXITCODE -ne 0) { throw "npm ci falhou" }
    & npm run vendor:sync
    if ($LASTEXITCODE -ne 0) { throw "npm run vendor:sync falhou" }
    & php bin/minify-css.php
    if ($LASTEXITCODE -ne 0) { throw "minify-css falhou" }
    Write-Host "      asset-map:compile" -ForegroundColor DarkGray
    & php bin/console asset-map:compile --env=prod --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "asset-map:compile falhou" }
} else {
    Write-Host "[skip] build local (--SkipBuild)" -ForegroundColor Yellow
}

Write-Host "[3/4] empacotar e enviar $ArchiveName" -ForegroundColor Cyan
$archive = Join-Path $env:TEMP $ArchiveName
if (Test-Path $archive) { Remove-Item $archive -Force }

$tarArgs = @(
    "--exclude=.git",
    "--exclude=.github",
    "--exclude=node_modules",
    "--exclude=.env",
    "--exclude=.env.*",
    "--exclude=var/cache",
    "--exclude=var/log",
    "--exclude=public/uploads",
    "--exclude=tests",
    "--exclude=docs",
    "--exclude=services",
    "--exclude=.cursor",
    "--exclude=deploy.tar.gz",
    "--exclude=deploy-uniosaude.tar.gz",
    "--exclude=phpunit.xml.dist",
    "--exclude=.phpunit.cache",
    "-czf", $archive,
    "."
)
& tar @tarArgs
if ($LASTEXITCODE -ne 0) { throw "tar falhou" }

$archiveSizeMb = [math]::Round((Get-Item $archive).Length / 1MB, 1)
Write-Host "      arquivo: $archive (${archiveSizeMb} MB)" -ForegroundColor DarkGray

Invoke-Retry {
    & scp @scpBase $archive "${sshTarget}:/tmp/$ArchiveName"
    if ($LASTEXITCODE -ne 0) { throw "scp archive falhou" }
}

$gitSha = ""
$gitRef = ""
try {
    $gitSha = (& git rev-parse HEAD).Trim()
    $gitRef = (& git rev-parse --abbrev-ref HEAD).Trim()
} catch {
    $gitSha = "manual"
    $gitRef = "manual"
}

$remoteEnvFile = Join-Path $env:TEMP "deploy-remote.env"
$envLines = @(
    "DEPLOY_PATH=$deployPath"
    "PUBLIC_HTML=$publicHtml"
    "GITHUB_SHA=$gitSha"
    "GITHUB_REF_NAME=$gitRef"
)
[System.IO.File]::WriteAllText($remoteEnvFile, ($envLines -join "`n") + "`n", [System.Text.UTF8Encoding]::new($false))

Invoke-Retry {
    & scp @scpBase $remoteEnvFile "${sshTarget}:/tmp/deploy-remote.env"
    if ($LASTEXITCODE -ne 0) { throw "scp deploy-remote.env falhou" }
    & scp @scpBase "$root\scripts\ci-remote-extract.sh" "${sshTarget}:/tmp/ci-remote-extract.sh"
    if ($LASTEXITCODE -ne 0) { throw "scp ci-remote-extract.sh falhou" }
}

Write-Host "[4/4] extrair no servidor + deploy-server.sh" -ForegroundColor Cyan
Invoke-Retry {
    & ssh @sshBase $sshTarget "ARCHIVE=$ArchiveName bash /tmp/ci-remote-extract.sh"
    if ($LASTEXITCODE -ne 0) { throw "deploy remoto falhou" }
}

$reportLocal = "$root\deploy-reports\deploy-report.txt"
New-Item -ItemType Directory -Force -Path (Split-Path $reportLocal) | Out-Null
& scp @scpBase "${sshTarget}:${deployPath}/var/log/deploy-report.txt" $reportLocal 2>$null
if (Test-Path $reportLocal) {
    Write-Host ""
    Write-Host "========== DEPLOY REPORT ==========" -ForegroundColor Cyan
    Get-Content $reportLocal
}

$defaultUri = $cfg["DEFAULT_URI"]
if (-not $defaultUri) { $defaultUri = "https://uniosaude.uniowork.com.br" }
Write-Host ""
Write-Host "Smoke: $defaultUri/login" -ForegroundColor Cyan
try {
    $resp = Invoke-WebRequest -Uri "$defaultUri/login" -Method Head -UseBasicParsing -TimeoutSec 30
    Write-Host "[ok] HTTP $($resp.StatusCode)" -ForegroundColor Green
} catch {
    if ($_.Exception.Response) {
        $code = [int]$_.Exception.Response.StatusCode
        Write-Host "[aviso] HTTP $code — confira SSL/vhost se necessario" -ForegroundColor Yellow
    } else {
        Write-Host "[aviso] smoke externo falhou: $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Deploy manual concluido." -ForegroundColor Green
Write-Host "URL: $defaultUri" -ForegroundColor Green
