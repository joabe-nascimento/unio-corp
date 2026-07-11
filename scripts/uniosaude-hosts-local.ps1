# DNS local enquanto roteador nao propaga (uniowork + uniosaude).
#
# Execute como Administrador:
#   powershell -ExecutionPolicy Bypass -File .\scripts\uniosaude-hosts-local.ps1

$hostsPath = "$env:windir\System32\drivers\etc\hosts"
$ip = "50.6.138.130"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Execute como Administrador:"
    Write-Host "  1. Win+X -> Terminal (Admin)"
    Write-Host "  2. cd '$PSScriptRoot\..'"
    Write-Host "  3. powershell -ExecutionPolicy Bypass -File .\scripts\uniosaude-hosts-local.ps1"
    exit 1
}

$entries = @(
    "$ip uniowork.com.br"
    "$ip www.uniowork.com.br"
    "$ip uniosaude.uniowork.com.br"
)

$content = Get-Content $hostsPath -ErrorAction Stop
$filtered = $content | Where-Object {
    $_ -notmatch 'uniowork\.com\.br' -and $_ -notmatch 'uniosaude\.uniowork\.com\.br'
}
$filtered + "" + $entries | Set-Content $hostsPath -Encoding ASCII

Write-Host "Hosts atualizado."
Write-Host "Teste: https://uniosaude.uniowork.com.br/login"
Write-Host "Remover depois: apague as linhas uniowork/uniosaude do arquivo hosts."
