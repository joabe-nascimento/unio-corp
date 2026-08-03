# Checklist local da Sasha: JurisFlow + RAG sync + smoke test
#
# Uso (na raiz do repo Unio):
#   powershell -ExecutionPolicy Bypass -File scripts\setup-sasha-local.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\setup-sasha-local.ps1 -SkipRag
#   powershell -ExecutionPolicy Bypass -File scripts\setup-sasha-local.ps1 -SkipStart

param(
    [string]$JurisFlowPath = "",
    [string]$LegalAiUrl = "http://127.0.0.1:8090",
    [switch]$SkipStart,
    [switch]$SkipRag,
    [switch]$SkipChatTest
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Write-Step($n, $total, $msg) {
    Write-Host ""
    Write-Host "[$n/$total] $msg" -ForegroundColor Cyan
}
function Write-Ok($msg) { Write-Host "  OK  $msg" -ForegroundColor Green }
function Write-Warn($msg) { Write-Host "  !!  $msg" -ForegroundColor Yellow }
function Write-Fail($msg) { Write-Host "  XX  $msg" -ForegroundColor Red }

$failed = $false
$total = 4

Write-Host ""
Write-Host "========================================" -ForegroundColor Magenta
Write-Host "  Setup Sasha local — Unio Juridico" -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

# 1) JurisFlow
Write-Step 1 $total "JurisFlow AI ($LegalAiUrl)"

if ($SkipStart) {
    try {
        $h = Invoke-RestMethod -Uri "$LegalAiUrl/health" -Method GET -TimeoutSec 5
        if ($h.status) { Write-Ok "Health: $($h.status)" } else { throw "health invalido" }
    } catch {
        Write-Fail "JurisFlow offline. Rode: scripts\start-jurisflow-local.ps1"
        $failed = $true
    }
} else {
    $startArgs = @("-ExecutionPolicy", "Bypass", "-File", (Join-Path $PSScriptRoot "start-jurisflow-local.ps1"))
    if ($JurisFlowPath) { $startArgs += @("-JurisFlowPath", $JurisFlowPath) }
    & powershell @startArgs
    if ($LASTEXITCODE -ne 0) { $failed = $true }
}

if (-not $failed) {
    try {
        $status = Invoke-RestMethod -Uri "$LegalAiUrl/v1/status" -Method GET -TimeoutSec 15
        Write-Ok "Status: $($status.status) | LLM: $($status.llm_provider) / $($status.llm_model)"
        Write-Host "       Documentos: $($status.total_documents) | Chunks: $($status.total_chunks)" -ForegroundColor DarkGray
        if ([int]$status.total_documents -eq 0) {
            Write-Warn "RAG vazio — rode o passo 2 (rag:sync) para a Sasha usar documentos do escritorio"
        }
    } catch {
        Write-Warn "Nao foi possivel ler /v1/status"
    }
}

# 2) RAG sync
Write-Step 2 $total "Sincronizar documentos no RAG (Symfony)"

if ($failed) {
    Write-Warn "Pulando — JurisFlow indisponivel"
} elseif ($SkipRag) {
    Write-Warn "Pulado (-SkipRag)"
} else {
    $ragOut = & php bin/console app:juridico:rag:sync 2>&1
    $ragExit = $LASTEXITCODE
    $ragText = ($ragOut | Out-String).Trim()
    if ($ragExit -eq 0) {
        Write-Ok "rag:sync concluido"
        if ($ragText -match '(\d+) documento') {
            Write-Host "       $ragText" -ForegroundColor DarkGray
        }
    } else {
        Write-Warn "rag:sync retornou codigo $ragExit (sem documentos ou servico indisponivel durante sync)"
        if ($ragText) { Write-Host "       $ragText" -ForegroundColor DarkGray }
    }
}

# 3) Config Symfony
Write-Step 3 $total "Configuracao LEGAL_AI no Symfony"

try {
    $envLines = & php bin/console debug:container --env-vars 2>&1 | Out-String
    if ($envLines -match 'LEGAL_AI_ENABLED\s+\S+\s+"?(true|false)"?') {
        $enabled = $Matches[1]
    } else { $enabled = "?" }
    if ($envLines -match 'LEGAL_AI_URL\s+\S+\s+"?([^"\r\n]+)"?') {
        $url = $Matches[1].Trim()
    } else { $url = "?" }

    if ($enabled -eq "true" -and $url -like "*8090*") {
        Write-Ok "LEGAL_AI_ENABLED=true | LEGAL_AI_URL=$url"
    } else {
        Write-Warn "Verifique .env.local: LEGAL_AI_ENABLED=true e LEGAL_AI_URL=http://127.0.0.1:8090"
    }
} catch {
    Write-Warn "Nao foi possivel ler env vars do Symfony"
}

# 4) Smoke test chat
Write-Step 4 $total "Smoke test — chat Sasha (JurisFlow direto)"

if ($failed) {
    Write-Warn "Pulando — JurisFlow indisponivel"
} elseif ($SkipChatTest) {
    Write-Warn "Pulado (-SkipChatTest)"
} else {
    $body = @{
        message       = "Responda em uma frase: voce esta online?"
        escritorio_id = "default"
        use_rag       = $true
        mode          = "standard"
        history       = @()
    } | ConvertTo-Json -Depth 4

    try {
        $chat = Invoke-RestMethod -Uri "$LegalAiUrl/v1/assistant/Sasha/chat" -Method POST `
            -Body $body -ContentType "application/json" -TimeoutSec 90
        $answer = ($chat.answer | Out-String).Trim()
        if ($answer.Length -gt 0) {
            $preview = if ($answer.Length -gt 120) { $answer.Substring(0, 120) + "..." } else { $answer }
            Write-Ok "Chat respondeu"
            Write-Host "       $preview" -ForegroundColor DarkGray
        } else {
            Write-Fail "Resposta vazia do JurisFlow"
            $failed = $true
        }
    } catch {
        Write-Fail "Falha no chat: $($_.Exception.Message)"
        $failed = $true
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Magenta
if ($failed) {
    Write-Host "  Setup incompleto — corrija os itens acima" -ForegroundColor Red
    exit 1
}

Write-Host "  Sasha pronta no local!" -ForegroundColor Green
Write-Host "  Abra http://127.0.0.1:8000 e teste o chat." -ForegroundColor Green
Write-Host ""
Write-Host "  Dicas para mais inteligencia:" -ForegroundColor DarkGray
Write-Host "  - Cadastre documentos e rode rag:sync de novo" -ForegroundColor DarkGray
Write-Host "  - Use o botao LEX para raciocinio superior" -ForegroundColor DarkGray
Write-Host "  - Mantenha processos/prazos atualizados no Unio" -ForegroundColor DarkGray
Write-Host "========================================" -ForegroundColor Magenta
exit 0
