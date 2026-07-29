# Deploy manual Unio Juridico — PC (Windows) -> HostGator via SSH.
# Espelha o pipeline do GitHub Actions sem depender do Actions.
#
# Uso (na raiz do repo):
#   powershell -ExecutionPolicy Bypass -File scripts\deploy-uniojuridico-manual.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\deploy-uniojuridico-manual.ps1 -Fast
#
# Pre-requisitos:
#   - OpenSSH (ssh/scp) no PATH
#   - tar (Windows 10+)
#   - composer, npm, php
#   - config/deploy-uniojuridico.local.env com DEPLOY_KEY_FILE
#
# Modos:
#   (padrao)     build completo local + deploy remoto completo
#   -SkipBuild   pula composer/npm local (so CSS/templates alterados)
#   -Fast        pula build local, BOM, smoke e passos lentos no servidor (~3-5 min)

param(
    [switch]$SkipBuild,
    [switch]$Fast,
    [switch]$SkipBomCheck,
    [switch]$SkipSmoke,
    [string]$ArchiveName = "deploy-uniojuridico.tar.gz"
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

function Test-NeedsNpmInstall {
    param([string]$ProjectRoot)
    $lock = Join-Path $ProjectRoot "package-lock.json"
    $nodeModules = Join-Path $ProjectRoot "node_modules"
    $marker = Join-Path $ProjectRoot "var\.deploy-npm-lock-hash"
    if (-not (Test-Path $lock)) { return $false }
    if (-not (Test-Path $nodeModules)) { return $true }
    $hash = (Get-FileHash $lock -Algorithm SHA256).Hash
    if (Test-Path $marker) {
        $prev = (Get-Content $marker -Raw).Trim()
        if ($prev -eq $hash) { return $false }
    }
    return $true
}

function Save-NpmLockHash {
    param([string]$ProjectRoot)
    $lock = Join-Path $ProjectRoot "package-lock.json"
    $marker = Join-Path $ProjectRoot "var\.deploy-npm-lock-hash"
    if (-not (Test-Path $lock)) { return }
    New-Item -ItemType Directory -Force -Path (Split-Path $marker) | Out-Null
    (Get-FileHash $lock -Algorithm SHA256).Hash | Set-Content $marker -NoNewline
}

function Invoke-ProjectBuild {
    param([string]$ProjectRoot)
    Push-Location $ProjectRoot
    try {
        Write-Host "      composer install (prod, no-dev)" -ForegroundColor DarkGray
        & composer install --no-dev --no-progress --prefer-dist --optimize-autoloader --no-scripts
        if ($LASTEXITCODE -ne 0) { throw "composer install falhou" }

        if (Test-NeedsNpmInstall -ProjectRoot $ProjectRoot) {
            Write-Host "      npm ci (package-lock alterado)" -ForegroundColor DarkGray
            & npm ci --prefer-offline --no-audit --no-fund
            if ($LASTEXITCODE -ne 0) { throw "npm ci falhou" }
            Save-NpmLockHash -ProjectRoot $ProjectRoot
        } else {
            Write-Host "      npm ci (skip — lock inalterado)" -ForegroundColor DarkGray
        }

        & npm run vendor:sync
        if ($LASTEXITCODE -ne 0) { throw "npm run vendor:sync falhou" }
        & php bin/minify-css.php
        if ($LASTEXITCODE -ne 0) { throw "minify-css falhou" }
        Write-Host "      asset-map:compile" -ForegroundColor DarkGray
        & php bin/console asset-map:compile --env=prod --no-interaction
        if ($LASTEXITCODE -ne 0) { throw "asset-map:compile falhou" }
    } finally {
        Pop-Location
    }
}

function New-DeployStagingDir {
    param([string]$ProjectRoot)
    $staging = Join-Path $env:TEMP ("unio-juridico-staging-" + [Guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory -Path $staging | Out-Null
    $stageTar = Join-Path $env:TEMP ("unio-juridico-stage-src-" + [Guid]::NewGuid().ToString("N") + ".tar")
    Push-Location $ProjectRoot
    try {
        & tar --exclude=node_modules --exclude=.git --exclude=var/cache --exclude=var/log `
            --exclude=public/uploads --exclude=tests --exclude=docs `
            -cf $stageTar .
        if ($LASTEXITCODE -ne 0) { throw "tar staging falhou" }
        Push-Location $staging
        & tar -xf $stageTar
        if ($LASTEXITCODE -ne 0) { throw "tar extract staging falhou" }
    } finally {
        Pop-Location
        Remove-Item $stageTar -Force -ErrorAction SilentlyContinue
    }
    return $staging
}

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

if ($Fast) {
    $SkipBuild = $true
    $SkipBomCheck = $true
    $SkipSmoke = $true
}

Write-Host "== Deploy manual Unio Juridico (PC -> HostGator) ==" -ForegroundColor Cyan
if ($Fast) {
    Write-Host "Modo: FAST (sem build local, sem passos lentos no servidor)" -ForegroundColor Yellow
} elseif ($SkipBuild) {
    Write-Host "Modo: SkipBuild (sem composer/npm local)" -ForegroundColor Yellow
}

# Pre-deploy check: remover BOM automaticamente (skip em -Fast)
if (-not $SkipBomCheck) {
    Write-Host ""
    Write-Host "[0/5] Verificando e removendo BOM..." -ForegroundColor Yellow
    & "$PSScriptRoot/remove-bom.ps1"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Falha ao remover BOM" -ForegroundColor Red
        exit 1
    }
    Write-Host ""
} else {
    Write-Host "[skip] verificacao BOM" -ForegroundColor DarkGray
}

$cfg = @{}
Load-EnvFile "$root\config\deploy-hostgator.defaults.env" $cfg
$localEnv = "$root\config\deploy-uniojuridico.local.env"
if (-not (Test-Path $localEnv)) {
    Write-Host ""
    Write-Host "ERRO: crie config/deploy-uniojuridico.local.env" -ForegroundColor Red
    Write-Host "      Copie de config/deploy-uniojuridico.local.env.example" -ForegroundColor Red
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
if (-not $deployPath) { $deployPath = "/home2/joabef36/unio-uniojuridico" }
$publicHtml = $cfg["DEPLOY_PUBLIC_HTML"]
if (-not $publicHtml) { $publicHtml = "/home2/joabef36/uniojuridico.uniowork.com.br" }
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

$stagingDir = $null
$assetsPrecompiled = "0"

if (-not $SkipBuild) {
    Write-Host ""
    Write-Host "[1/4] build em staging (nao altera vendor local)" -ForegroundColor Cyan
    $stagingDir = New-DeployStagingDir -ProjectRoot $root
    try {
        Invoke-ProjectBuild -ProjectRoot $stagingDir
        $tarSource = $stagingDir
        $assetsPrecompiled = "1"
    } catch {
        if ($stagingDir -and (Test-Path $stagingDir)) {
            Remove-Item $stagingDir -Recurse -Force -ErrorAction SilentlyContinue
        }
        throw
    }
} else {
    Write-Host "[skip] build local" -ForegroundColor Yellow
    $tarSource = $root
    $assetsPrecompiled = if ($Fast) { "1" } else { "0" }
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
    "--exclude=deploy-uniojuridico.tar.gz",
    "--exclude=phpunit.xml.dist",
    "--exclude=.phpunit.cache",
    "-czf", $archive,
    "."
)
Push-Location $tarSource
try {
    & tar @tarArgs
    if ($LASTEXITCODE -ne 0) { throw "tar falhou" }
} finally {
    Pop-Location
    if ($stagingDir -and (Test-Path $stagingDir)) {
        Remove-Item $stagingDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

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
$fastDeploy = if ($Fast) { "1" } else { "0" }
$envLines = @(
    "DEPLOY_PATH=$deployPath"
    "PUBLIC_HTML=$publicHtml"
    "GITHUB_SHA=$gitSha"
    "GITHUB_REF_NAME=$gitRef"
    "SKIP_UNIO_PLATFORM_STEPS=1"
    "FAST_DEPLOY=$fastDeploy"
    "ASSETS_PRECOMPILED=$assetsPrecompiled"
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
if (-not $defaultUri) { $defaultUri = "https://uniojuridico.uniowork.com.br" }
if (-not $SkipSmoke) {
    Write-Host ""
    Write-Host "Smoke: $defaultUri/login" -ForegroundColor Cyan
    try {
        $resp = Invoke-WebRequest -Uri "$defaultUri/login" -Method Head -UseBasicParsing -TimeoutSec 15
        Write-Host "[ok] HTTP $($resp.StatusCode)" -ForegroundColor Green
    } catch {
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            Write-Host "[aviso] HTTP $code — confira SSL/vhost se necessario" -ForegroundColor Yellow
        } else {
            Write-Host "[aviso] smoke externo falhou: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }
} else {
    Write-Host "[skip] smoke test externo" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "Deploy manual concluido." -ForegroundColor Green
Write-Host "URL: $defaultUri" -ForegroundColor Green
