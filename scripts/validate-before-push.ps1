# Validação antes de push/deploy (Windows).
# Uso: .\scripts\validate-before-push.ps1
#      .\scripts\validate-before-push.ps1 -Quick
#      $env:VALIDATE_FRESH_DB = "1"; .\scripts\validate-before-push.ps1

param(
    [switch]$Quick,
    [switch]$SkipPhpStan,
    [switch]$SkipTests,
    [switch]$SkipAssets,
    [switch]$SkipDb,
    [switch]$FreshDb
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

if ($Quick) { $env:QUICK = "1" }
if ($SkipPhpStan) { $env:SKIP_PHPSTAN = "1" }
if ($SkipTests) { $env:SKIP_TESTS = "1" }
if ($SkipAssets) { $env:SKIP_ASSETS = "1" }
if ($SkipDb) { $env:SKIP_DB = "1" }
if ($FreshDb) { $env:VALIDATE_FRESH_DB = "1" }

$bashCandidates = @(
    "${env:ProgramFiles}\Git\bin\bash.exe",
    "${env:ProgramFiles}\Git\usr\bin\bash.exe"
)

foreach ($bash in $bashCandidates) {
    if (Get-Command $bash -ErrorAction SilentlyContinue) {
        & $bash scripts/validate-before-push.sh
        exit $LASTEXITCODE
    }
}

Write-Host "Git Bash não encontrado — executando checks PHP mínimos..." -ForegroundColor Yellow

php bin/console lint:container --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php bin/console lint:yaml config --parse-tags --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php bin/console lint:twig templates --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

composer validate --no-check-publish --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if (-not $SkipDb) {
    php bin/console app:validate-system --no-interaction
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if (-not $Quick -and -not $SkipTests) {
    $env:APP_ENV = "test"
    php bin/phpunit
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "Validação concluída — OK." -ForegroundColor Green
