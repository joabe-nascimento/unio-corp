# Sobe o JurisFlow AI (motor da Sasha) em http://127.0.0.1:8090
#
# Uso (na raiz do repo Unio):
#   powershell -ExecutionPolicy Bypass -File scripts\start-jurisflow-local.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\start-jurisflow-local.ps1 -WaitOnly
#
# Variável de ambiente opcional:
#   $env:JURISFLOW_AI_PATH = "C:\caminho\JurisFlow-ai-service"

param(
    [string]$JurisFlowPath = "",
    [int]$Port = 8090,
    [int]$WaitSeconds = 120,
    [switch]$WaitOnly
)

$ErrorActionPreference = "Stop"
$ListenIp = "127.0.0.1"

function Write-Step($msg) { Write-Host $msg -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host "  OK  $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "  !!  $msg" -ForegroundColor Yellow }

function Get-JurisFlowServicePath {
    param([string]$Preferred)

    $candidates = @()
    if ($Preferred) { $candidates += $Preferred }
    if ($env:JURISFLOW_AI_PATH) { $candidates += $env:JURISFLOW_AI_PATH }
    $candidates += @(
        "C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service",
        "C:\projetos\Nova pasta\JurisFlow-ai-service"
    )

    foreach ($path in $candidates | Select-Object -Unique) {
        if (-not (Test-Path $path)) { continue }
        $main = Join-Path $path "app\main.py"
        if (-not (Test-Path $main)) { continue }
        $content = Get-Content $main -Raw -ErrorAction SilentlyContinue
        if ($content -match '/v1/assistant/Sasha/chat') {
            return (Resolve-Path $path).Path
        }
        Write-Warn "Ignorando $path (API antiga — use o repo com app/main.py e /v1/assistant/Sasha/chat)"
    }

    return $null
}

function Test-JurisFlowHealth {
    param([string]$BaseUrl)
    try {
        $r = Invoke-RestMethod -Uri "$BaseUrl/health" -Method GET -TimeoutSec 5
        return ($null -ne $r.status)
    } catch {
        return $false
    }
}

$baseUrl = "http://$ListenIp`:$Port"
$servicePath = Get-JurisFlowServicePath -Preferred $JurisFlowPath

if (-not $servicePath) {
    Write-Host ""
    Write-Host "JurisFlow AI nao encontrado." -ForegroundColor Red
    Write-Host "Defina JURISFLOW_AI_PATH ou clone o repo completo (app/main.py + /v1/assistant/Sasha/chat)." -ForegroundColor Yellow
    exit 1
}

Write-Step ""
Write-Step "JurisFlow AI — start local"
Write-Step "Repo: $servicePath"
Write-Step "URL:  $baseUrl"
Write-Step ""

if (Test-JurisFlowHealth -BaseUrl $baseUrl) {
    Write-Ok "Servico ja esta online em $baseUrl"
    try {
        $status = Invoke-RestMethod -Uri "$baseUrl/v1/status" -Method GET -TimeoutSec 10
        Write-Host "       LLM: $($status.llm_provider) / $($status.llm_model)" -ForegroundColor DarkGray
        Write-Host "       Docs indexados: $($status.total_documents) | Chunks: $($status.total_chunks)" -ForegroundColor DarkGray
    } catch { }
    exit 0
}

if ($WaitOnly) {
    Write-Warn "Servico offline e -WaitOnly informado. Nada a fazer."
    exit 1
}

Write-Step "Iniciando uvicorn (primeira subida pode levar ~1 min)..."

$logDir = Join-Path (Split-Path -Parent $PSScriptRoot) "var"
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir | Out-Null }
$logFile = Join-Path $logDir "jurisflow-local.log"

$argList = "-NoExit", "-ExecutionPolicy", "Bypass", "-Command", (
    "Set-Location '$servicePath'; " +
    "`$host.UI.RawUI.WindowTitle = 'JurisFlow AI — porta $Port'; " +
    "python -m uvicorn app.main:app --host $ListenIp --port $Port 2>&1 | Tee-Object -FilePath '$logFile'"
)

Start-Process -FilePath "powershell.exe" -ArgumentList $argList | Out-Null

$deadline = (Get-Date).AddSeconds($WaitSeconds)
while ((Get-Date) -lt $deadline) {
    Start-Sleep -Seconds 3
    if (Test-JurisFlowHealth -BaseUrl $baseUrl) {
        Write-Ok "Servico online em $baseUrl"
        Write-Host "       Log: $logFile" -ForegroundColor DarkGray
        Write-Host "       Proximo passo: powershell -ExecutionPolicy Bypass -File scripts\setup-sasha-local.ps1 -SkipStart" -ForegroundColor DarkGray
        exit 0
    }
    $remaining = [int]($deadline - (Get-Date)).TotalSeconds
    Write-Host "  ... aguardando (${remaining}s restantes)" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "Timeout: JurisFlow nao respondeu em ${WaitSeconds}s." -ForegroundColor Red
Write-Host "Veja o log: $logFile" -ForegroundColor Yellow
exit 1
