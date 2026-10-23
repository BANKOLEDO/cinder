$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$version = '8.4.27'
$expectedSha256 = '93bd8a815a52156606fb906d0573437908c61f96e2cc52c03a51e36a41bf5236'
$destination = Join-Path $env:LOCALAPPDATA "Cinder\php-$version"
$php = Join-Path $destination 'php.exe'

if (Test-Path -LiteralPath $php) {
    Write-Host "Patched PHP $version is already installed at $destination"
    exit 0
}

New-Item -ItemType Directory -Path $destination -Force | Out-Null
$zip = Join-Path $env:TEMP "cinder-php-$version.zip"
$url = "https://windows.php.net/downloads/releases/php-$version-nts-Win32-vs17-x64.zip"

Write-Host "Downloading PHP $version (patched for PHP2Shell)..."
Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
$actualSha256 = (Get-FileHash -Algorithm SHA256 -LiteralPath $zip).Hash.ToLowerInvariant()

if ($actualSha256 -ne $expectedSha256) {
    Remove-Item -LiteralPath $zip -Force
    throw "Checksum mismatch for $url. Refusing to install."
}

Expand-Archive -LiteralPath $zip -DestinationPath $destination -Force
Remove-Item -LiteralPath $zip -Force
Write-Host "Installed PHP $version to $destination"
