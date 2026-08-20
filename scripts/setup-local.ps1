param(
    [switch]$SkipNodeModules,
    [switch]$SkipPhpDependencies,
    [switch]$SkipDatabase
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

function Resolve-MySqlPath() {
    if (Test-Path 'C:\xampp\mysql\bin\mysql.exe') {
        return 'C:\xampp\mysql\bin\mysql.exe'
    }
    $command = Get-Command mysql -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }
    return $null
}

$phpPath = Resolve-PhpPath
$npmCmd = Resolve-NpmCmd
$mysqlPath = Resolve-MySqlPath

Write-Host 'Preparing Electava local environment...' -ForegroundColor Cyan

if (-not $SkipNodeModules) {
    if (-not (Test-Path (Join-Path $ApiDir 'node_modules'))) {
        Write-Host 'Installing API node modules...' -ForegroundColor Cyan
        Push-Location $ApiDir
        & $npmCmd install
        Pop-Location
    }

    if (-not (Test-Path (Join-Path $UserDir 'node_modules'))) {
        Write-Host 'Installing website node modules...' -ForegroundColor Cyan
        Push-Location $UserDir
        & $npmCmd install
        Pop-Location
    }
}

if (-not $SkipPhpDependencies) {
    $composerPhar = Join-Path $WorkspaceDir 'composer.phar'
    if (-not (Test-Path $composerPhar)) {
        throw "workspace/composer.phar was not found. Place Composer there before running setup."
    }

    if (-not (Test-Path (Join-Path $WorkspaceDir 'packages\\autoload.php'))) {
        Write-Host 'Installing workspace PHP dependencies...' -ForegroundColor Cyan
        Push-Location $WorkspaceDir
        & $phpPath $composerPhar install --no-dev
        Pop-Location
    }
}

if (-not $SkipDatabase) {
    if (-not $mysqlPath) {
        throw 'MySQL CLI was not found. Install XAMPP/MySQL or add mysql to PATH.'
    }

    Write-Host 'Preparing electava_workspace database...' -ForegroundColor Cyan
    & $mysqlPath -u root -e "CREATE DATABASE IF NOT EXISTS electava_workspace;" | Out-Null

    $schemaPath = Join-Path $WorkspaceDir 'install.sql'
    $trackingPath = Join-Path $WorkspaceDir 'tracking.sql'

    cmd.exe /c "`"$mysqlPath`" -u root electava_workspace < `"$schemaPath`""
    if (Test-Path $trackingPath) {
        cmd.exe /c "`"$mysqlPath`" -u root electava_workspace < `"$trackingPath`""
    }
}

Write-Host 'Electava local setup completed.' -ForegroundColor Green
