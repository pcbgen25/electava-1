param(
    [switch]$RunFrontendBuild
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$ApiDir = Join-Path $RepoRoot 'API'
$UserDir = Join-Path $RepoRoot 'user'
$WorkspaceDir = Join-Path $RepoRoot 'workspace'

function Resolve-PhpPath() {
    if (Test-Path 'C:\xampp\php\php.exe') {
        return 'C:\xampp\php\php.exe'
    }
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }
    throw 'PHP was not found. Install XAMPP or add php to PATH.'
}

function Resolve-NpmCmd() {
    $command = Get-Command npm.cmd -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }
    if (Test-Path 'C:\Program Files\nodejs\npm.cmd') {
        return 'C:\Program Files\nodejs\npm.cmd'
    }
    throw 'npm.cmd was not found. Install Node.js first.'
}

$phpPath = Resolve-PhpPath
$npmCmd = Resolve-NpmCmd

Write-Host 'Validating Electava local codebase...' -ForegroundColor Cyan

$phpFiles = @()
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'includes') -Filter *.php -File -Recurse
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'core_admin') -Filter *.php -File -Recurse
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'admin') -Filter *.php -File -Recurse
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'employee') -Filter *.php -File -Recurse
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'service_team') -Filter *.php -File -Recurse
$phpFiles += Get-ChildItem (Join-Path $WorkspaceDir 'vendor') -Filter *.php -File -Depth 1
$phpFiles += Get-ChildItem $WorkspaceDir -Filter *.php -File
$phpFiles = $phpFiles | Sort-Object FullName -Unique

foreach ($file in $phpFiles) {
    & $phpPath -l $file.FullName | Out-Null
}
Write-Host "PHP lint passed for $($phpFiles.Count) workspace files." -ForegroundColor Green

Push-Location $ApiDir
& $npmCmd run check
Pop-Location
Write-Host 'API syntax checks passed.' -ForegroundColor Green

if ($RunFrontendBuild) {
    Push-Location $UserDir
    & $npmCmd run build
    Pop-Location
    Write-Host 'Frontend production build passed.' -ForegroundColor Green
} else {
    Write-Host 'Frontend build skipped. Use -RunFrontendBuild to include it.' -ForegroundColor Yellow
}

$urls = @(
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5000/health',
    'http://127.0.0.1:8000/login.php'
)

foreach ($url in $urls) {
    try {
        $status = (Invoke-WebRequest -UseBasicParsing $url -TimeoutSec 5).StatusCode
        Write-Host "HTTP $status - $url" -ForegroundColor Green
    } catch {
        Write-Host "Not running or not reachable yet - $url" -ForegroundColor Yellow
    }
}

Write-Host 'Electava validation finished.' -ForegroundColor Green
