param([int]$Port = 8099)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Join-Path $env:LOCALAPPDATA 'Cinder\php-8.4.27\php.exe'

if (-not (Test-Path -LiteralPath $php)) {
    & (Join-Path $root 'setup-php.ps1')
}

$phpDirectory = Split-Path -Parent $php
$extensions = Join-Path $phpDirectory 'ext'
$dataDirectory = Join-Path $root 'data'
New-Item -ItemType Directory -Path $dataDirectory -Force | Out-Null

Write-Host "Cinder running at http://127.0.0.1:$Port"
Write-Host "Press Ctrl+C to stop."

& $php `
    -c (Join-Path $root 'php.ini') `
    -d "extension_dir=$extensions" `
    -d extension=pdo_sqlite `
    -d extension=sodium `
    -d extension=mbstring `
    -S "127.0.0.1:$Port" `
    -t (Join-Path $root 'public') `
    (Join-Path $root 'router.php')
