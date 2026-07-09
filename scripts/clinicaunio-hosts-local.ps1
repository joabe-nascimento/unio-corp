# DNS local enquanto roteador nao propaga (uniowork + clinicaunio).
# Abra PowerShell COMO ADMINISTRADOR (botao direito -> Executar como administrador).
#   cd C:\projetos\unio-corp\unio-corp
#   powershell -ExecutionPolicy Bypass -File .\scripts\clinicaunio-hosts-local.ps1

$ErrorActionPreference = 'Stop'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host ""
    Write-Host "ERRO: execute o PowerShell como Administrador." -ForegroundColor Red
    Write-Host "  1. Menu Iniciar -> digite PowerShell"
    Write-Host "  2. Botao direito -> Executar como administrador"
    Write-Host "  3. cd C:\projetos\unio-corp\unio-corp"
    Write-Host "  4. powershell -ExecutionPolicy Bypass -File .\scripts\clinicaunio-hosts-local.ps1"
    Write-Host ""
    Write-Host "Alternativa (sem hosts): Configuracoes -> Rede -> Wi-Fi -> DNS manual -> 8.8.8.8 e 8.8.4.4"
    exit 1
}
$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$ip = '50.6.138.130'
$entries = @(
    "$ip uniowork.com.br",
    "$ip www.uniowork.com.br",
    "$ip clinicaunio.uniowork.com.br"
)

if (-not (Test-Path $hostsPath)) {
    Write-Error "Arquivo hosts nao encontrado: $hostsPath"
}

$lines = Get-Content $hostsPath
$filtered = $lines | Where-Object {
    $_ -notmatch 'uniowork\.com\.br' -and $_ -notmatch 'clinicaunio\.uniowork\.com\.br'
}
($filtered + $entries) | Set-Content -Path $hostsPath -Encoding ASCII

$entries | ForEach-Object { Write-Host "OK: $_" }
Write-Host "Teste: https://clinicaunio.uniowork.com.br/login"
Write-Host "Remover depois: apague as linhas uniowork/clinicaunio do arquivo hosts."
