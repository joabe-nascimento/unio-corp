# Commit automático + push + deploy Unio Jurídico
#
# Uso (na raiz do repo):
#   powershell -ExecutionPolicy Bypass -File scripts\ship-uniojuridico.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\ship-uniojuridico.ps1 -SkipDeploy
#   powershell -ExecutionPolicy Bypass -File scripts\ship-uniojuridico.ps1 -Fast -SkipSmoke
#
# O script lê git status/diff, monta a mensagem de commit e publica.

param(
    [switch]$SkipDeploy,
    [switch]$Fast,
    [switch]$SkipSmoke
)

$ErrorActionPreference = "Stop"

function Invoke-Git {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $out = & git @args 2>&1
    $ErrorActionPreference = $prev
    return $out
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Get-ChangeSummary {
    $porcelain = Invoke-Git status --porcelain
    if (-not $porcelain) { return $null }

    $files = @($porcelain | ForEach-Object {
        $line = $_.Trim()
        if ($line.Length -lt 4) { return }
        $line.Substring(3).Trim('"')
    } | Where-Object { $_ })

    $stat = (Invoke-Git diff --stat HEAD | Out-String).Trim()
    if (-not $stat) { $stat = (Invoke-Git diff --stat | Out-String).Trim() }

    $themes = [ordered]@{}
    $add = {
        param($key, $detail)
        if (-not $themes.Contains($key)) { $themes[$key] = [System.Collections.Generic.List[string]]::new() }
        if ($detail -and -not $themes[$key].Contains($detail)) { [void]$themes[$key].Add($detail) }
    }

    foreach ($f in $files) {
        switch -Regex ($f) {
            'publicacoes' { & $add 'Publicações DJEN' 'listagem com banner e KPIs padronizados' }
            'atendimento_whatsapp_chat|jur-wa-chat' { & $add 'Chat WhatsApp' 'bolhas, alinhamento e tipografia' }
            'atendimento_list' { & $add 'Central de Atendimento' 'banner jur-proc-hero e kpi_row' }
            'clientes_list' { & $add 'CRM Clientes' 'banner e KPIs' }
            'prazos_list' { & $add 'Prazos' 'banner e KPIs' }
            'documentos_list' { & $add 'GED Documentos' 'banner e KPIs' }
            'honorarios_list' { & $add 'Honorários' 'banner e KPIs' }
            'jurisprudencia_list' { & $add 'Jurisprudência IA' 'banner e KPIs' }
            'JuridicoPublicacao' { & $add 'Backend publicações' 'métricas vinculadas e total ativas' }
            'unio-juridico-theme' { & $add 'Tema jurídico' 'estilos do banner e badges' }
            'base\.html' { & $add 'Assets' 'cache-bust CSS' }
            default { & $add 'Outros' $f }
        }
    }

    $bullets = @()
    foreach ($kv in $themes.GetEnumerator()) {
        $details = ($kv.Value | Select-Object -Unique) -join '; '
        $bullets += "- $($kv.Key): $details"
    }

    $scope = switch ($themes.Count) {
        { $_ -ge 4 } { 'UI e padronização das telas jurídicas' }
        { $_ -ge 2 } { 'melhorias de UI jurídica' }
        default { ($themes.Keys | Select-Object -First 1) }
    }

    $title = "UI: $scope."
    if ($themes.Contains('Backend publicações')) {
        $title = "Feat: métricas de publicações e padronização de listagens jurídicas."
    }

    return [pscustomobject]@{
        Title   = $title
        Body    = ($bullets -join "`n")
        Files   = $files
        Stat    = $stat
        Count   = $files.Count
    }
}

Write-Host "== Ship Unio Jurídico ==" -ForegroundColor Cyan

$summary = Get-ChangeSummary
if (-not $summary) {
    Write-Host "Working tree limpo — nada para commitar." -ForegroundColor Yellow
    if (-not $SkipDeploy) {
        $deployArgs = @("-ExecutionPolicy", "Bypass", "-File", "scripts\deploy-uniojuridico-manual.ps1")
        if ($Fast) { $deployArgs += "-Fast" }
        if ($SkipSmoke) { $deployArgs += "-SkipSmoke" }
        & powershell @deployArgs
    }
    exit 0
}

Write-Host ""
Write-Host "Alterações detectadas ($($summary.Count) arquivo(s)):" -ForegroundColor Green
Write-Host $summary.Stat
Write-Host ""
Write-Host "Mensagem de commit:" -ForegroundColor Green
Write-Host "  $($summary.Title)"
Write-Host $summary.Body
Write-Host ""

git add -A
Invoke-Git commit -m $summary.Title -m $summary.Body | Out-Null
if ($LASTEXITCODE -ne 0) { throw "git commit falhou" }

Write-Host ""
Write-Host "Push para origin..." -ForegroundColor Cyan
Invoke-Git push -u origin HEAD | Out-Null
if ($LASTEXITCODE -ne 0) { throw "git push falhou" }

if ($SkipDeploy) {
    Write-Host "Deploy pulado (-SkipDeploy)." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "Deploy para HostGator..." -ForegroundColor Cyan
$deployArgs = @("-ExecutionPolicy", "Bypass", "-File", "scripts\deploy-uniojuridico-manual.ps1")
if ($Fast) { $deployArgs += "-Fast" }
if ($SkipSmoke) { $deployArgs += "-SkipSmoke" }
& powershell @deployArgs
if ($LASTEXITCODE -ne 0) { throw "deploy falhou" }

Write-Host ""
Write-Host "Concluído: commit + push + deploy." -ForegroundColor Green
