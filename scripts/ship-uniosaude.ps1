# Ship Unio Saude — detecta alteracoes, commit, push e deploy no HostGator.
#
# Uso (na raiz do repo unio-corp):
#   powershell -ExecutionPolicy Bypass -File scripts\ship-uniosaude.ps1
#
# Opcoes:
#   -Message "fix: ..."   mensagem manual (pula auto-mensagem)
#   -SkipBuild              forca deploy sem build local
#   -ForceBuild             forca composer/npm mesmo com so CSS/templates
#   -NoDeploy               so commit + push
#   -NoPush                 so commit (nao push / nao deploy)
#   -DryRun                 mostra o que faria, sem executar

param(
    [string]$Message = "",
    [switch]$SkipBuild,
    [switch]$ForceBuild,
    [switch]$NoDeploy,
    [switch]$NoPush,
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Ship Unio Saude ==" -ForegroundColor Cyan
Write-Host "Repo: $root" -ForegroundColor DarkGray

function Test-IsSecretPath {
    param([string]$Path)
    $p = $Path -replace '\\', '/'
    $patterns = @(
        '\.env$',
        '\.env\.',
        'deploy-.*\.local\.env$',
        'composer\.phar$',
        '\.p12$',
        'google-service-account\.json$',
        'mailbox-credentials',
        'credentials\.json$',
        'id_rsa',
        '\.pem$'
    )
    foreach ($re in $patterns) {
        if ($p -match $re) { return $true }
    }
    return $false
}

function Get-ChangedPaths {
    $paths = New-Object System.Collections.Generic.List[string]
    $raw = git status --porcelain -uall
    if (-not $raw) { return @() }
    foreach ($line in ($raw -split "`n")) {
        $line = $line.TrimEnd()
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        # Formato porcelain: XY PATH ou XY OLD -> NEW
        $path = $line.Substring(3).Trim()
        if ($path -match ' -> ') {
            $path = ($path -split ' -> ', 2)[1]
        }
        if ($path.StartsWith('"') -and $path.EndsWith('"')) {
            $path = $path.Substring(1, $path.Length - 2)
        }
        $paths.Add($path)
    }
    return $paths.ToArray()
}

function New-CommitMessage {
    param([string[]]$Paths)

    $hasCss = $false
    $hasTwig = $false
    $hasPhp = $false
    $hasJs = $false
    $hasTest = $false
    $hasScript = $false
    $hasConfig = $false
    $areas = New-Object System.Collections.Generic.HashSet[string]

    foreach ($p in $Paths) {
        $n = $p -replace '\\', '/'
        if ($n -match '\.css$') { $hasCss = $true }
        if ($n -match '\.twig$|\.html$') { $hasTwig = $true }
        if ($n -match '\.php$') {
            if ($n -match '^tests/') { $hasTest = $true } else { $hasPhp = $true }
        }
        if ($n -match '\.(js|mjs|ts)$') { $hasJs = $true }
        if ($n -match '^scripts/') { $hasScript = $true }
        if ($n -match '^config/') { $hasConfig = $true }

        if ($n -match 'sidebar|clinic-polish|organismo') { [void]$areas.Add('sidebar') }
        if ($n -match 'carteirinha|marketing-home|landing|idcard|comprovante') { [void]$areas.Add('landing/carteirinha') }
        if ($n -match 'pos-operatorio|painel|recepcao|comercial') { [void]$areas.Add('ops clinica') }
        if ($n -match 'beneficiary|paciente|benef-') { [void]$areas.Add('portal paciente') }
        if ($n -match 'deploy|ship-uniosaude') { [void]$areas.Add('deploy') }
        if ($n -match 'Wallet|wallet') { [void]$areas.Add('wallet') }
    }

    $type = 'chore'
    if ($hasTest -and -not ($hasPhp -or $hasCss -or $hasTwig -or $hasJs)) {
        $type = 'test'
    } elseif ($hasPhp -and -not ($hasCss -or $hasTwig) -and ($Paths | Where-Object { $_ -match 'Controller|Service|Entity|Migration' })) {
        $type = 'feat'
    } elseif ($hasCss -or $hasTwig) {
        $type = 'fix'
    } elseif ($hasPhp -or $hasJs) {
        $type = 'fix'
    } elseif ($hasScript -or $hasConfig) {
        $type = 'chore'
    }

    # Heuristica: se so CSS/template, prefere fix; se PHP novo de feature, feat
    $onlyFront = ($hasCss -or $hasTwig -or $hasJs) -and -not $hasPhp
    if ($onlyFront) { $type = 'fix' }

    $scopeParts = @($areas)
    if ($scopeParts.Count -eq 0) {
        if ($hasCss) { $scopeParts = @('css') }
        elseif ($hasTwig) { $scopeParts = @('templates') }
        elseif ($hasPhp) { $scopeParts = @('app') }
        else { $scopeParts = @('uniosaude') }
    }

    $scope = ($scopeParts | Select-Object -First 2) -join ', '
    $n = $Paths.Count
    $sample = ($Paths | Select-Object -First 3 | ForEach-Object { Split-Path $_ -Leaf }) -join ', '
    if ($n -gt 3) { $sample = "$sample…" }

    return "${type}: ${scope} (${n} arquivo(s): ${sample})"
}

function Test-NeedsFullBuild {
    param([string[]]$Paths)
    foreach ($p in $Paths) {
        $n = $p -replace '\\', '/'
        if ($n -match '^composer\.(json|lock)$') { return $true }
        if ($n -match '^package(-lock)?\.json$') { return $true }
        if ($n -match '^assets/') { return $true }
        if ($n -match '^src/') { return $true }
        if ($n -match '\.php$' -and $n -notmatch '^tests/') { return $true }
        if ($n -match '^config/(packages|routes|services)') { return $true }
    }
    return $false
}

# ── 1. Branch / status ──
$branch = (git rev-parse --abbrev-ref HEAD).Trim()
if ($branch -ne 'uniosaude') {
    Write-Host "Aviso: branch atual e '$branch' (esperado: uniosaude)." -ForegroundColor Yellow
}

$allPaths = Get-ChangedPaths
if ($allPaths.Count -eq 0) {
    $ahead = 0
    try {
        $counts = (git rev-list --left-right --count "origin/$branch...HEAD" 2>$null)
        if ($counts) {
            $parts = $counts.Trim() -split '\s+'
            if ($parts.Count -ge 2) { $ahead = [int]$parts[1] }
        }
    } catch { }

    if ($ahead -gt 0) {
        Write-Host "Working tree limpa; $ahead commit(s) locais ainda nao no remoto." -ForegroundColor Yellow
    } else {
        Write-Host "Nada para commitar e branch sincronizada." -ForegroundColor Green
        if (-not $NoDeploy -and -not $NoPush) {
            Write-Host "Prosseguindo so com deploy do HEAD atual..." -ForegroundColor Cyan
        } else {
            exit 0
        }
    }
}

$secretPaths = @($allPaths | Where-Object { Test-IsSecretPath $_ })
$safePaths = @($allPaths | Where-Object { -not (Test-IsSecretPath $_) })

if ($secretPaths.Count -gt 0) {
    Write-Host "Ignorando arquivos sensiveis:" -ForegroundColor Yellow
    $secretPaths | ForEach-Object { Write-Host "  - $_" -ForegroundColor DarkYellow }
}

Write-Host ""
Write-Host "Alteracoes detectadas ($($safePaths.Count)):" -ForegroundColor Cyan
$safePaths | ForEach-Object { Write-Host "  • $_" }

# ── 2. Commit ──
$didCommit = $false
if ($safePaths.Count -gt 0) {
    if ([string]::IsNullOrWhiteSpace($Message)) {
        $Message = New-CommitMessage -Paths $safePaths
    }
    Write-Host ""
    Write-Host "Commit: $Message" -ForegroundColor Green

    if ($DryRun) {
        Write-Host "[dry-run] git add + commit" -ForegroundColor DarkGray
    } else {
        foreach ($p in $safePaths) {
            git add -- $p
            if ($LASTEXITCODE -ne 0) { throw "git add falhou: $p" }
        }
        git commit -m $Message
        if ($LASTEXITCODE -ne 0) { throw "git commit falhou" }
        $didCommit = $true
        Write-Host "[ok] commit criado" -ForegroundColor Green
    }
} else {
    Write-Host "Nenhum arquivo seguro para commit." -ForegroundColor DarkGray
}

# ── 3. Push ──
if (-not $NoPush) {
    if ($DryRun) {
        Write-Host "[dry-run] git push -u origin HEAD" -ForegroundColor DarkGray
    } else {
        Write-Host ""
        Write-Host "Push para origin/$branch ..." -ForegroundColor Cyan
        git push -u origin HEAD
        if ($LASTEXITCODE -ne 0) { throw "git push falhou" }
        Write-Host "[ok] push concluido" -ForegroundColor Green
    }
} else {
    Write-Host "[skip] push (--NoPush)" -ForegroundColor Yellow
}

# ── 4. Deploy ──
if ($NoDeploy -or $NoPush) {
    Write-Host "[skip] deploy" -ForegroundColor Yellow
    exit 0
}

$pathsForBuild = if ($safePaths.Count -gt 0) { $safePaths } else { @() }
$autoSkip = -not (Test-NeedsFullBuild -Paths $pathsForBuild)
if ($ForceBuild) {
    $autoSkip = $false
}
if ($SkipBuild) {
    $autoSkip = $true
}

$deployArgs = @()
if ($autoSkip) {
    $deployArgs += '-SkipBuild'
    Write-Host ""
    Write-Host "Deploy com -SkipBuild (so front/assets ou sem mudancas PHP)." -ForegroundColor DarkGray
} else {
    Write-Host ""
    Write-Host "Deploy com build completo (composer/npm)." -ForegroundColor DarkGray
}

$deployScript = Join-Path $root "scripts\deploy-uniosaude-manual.ps1"
if (-not (Test-Path $deployScript)) {
    throw "Script de deploy nao encontrado: $deployScript"
}

if ($DryRun) {
    Write-Host "[dry-run] $deployScript $($deployArgs -join ' ')" -ForegroundColor DarkGray
    exit 0
}

Write-Host ""
Write-Host "Iniciando deploy..." -ForegroundColor Cyan
& powershell -ExecutionPolicy Bypass -File $deployScript @deployArgs
if ($LASTEXITCODE -ne 0) { throw "deploy falhou (exit $LASTEXITCODE)" }

Write-Host ""
Write-Host "Ship concluido." -ForegroundColor Green
Write-Host "URL: https://uniosaude.uniowork.com.br" -ForegroundColor Cyan
