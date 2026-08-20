param(
    [switch]$StartMySql
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
$ApiDir = Join-Path $RepoRoot 'API'
$UserDir = Join-Path $RepoRoot 'user'
$WorkspaceDir = Join-Path $RepoRoot 'workspace'
$LogDir = Join-Path $RepoRoot 'runtime-logs'

New-Item -ItemType Directory -Force $LogDir | Out-Null

function Test-ListeningPort([int]$Port) {
    try {
        return $null -ne (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1)
    } catch {
        return [bool]((netstat -ano | Select-String -Pattern "LISTENING\s+\d+$" | Select-String -Pattern ":$Port\s"))
    }
}

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

function Start-BackgroundService([string]$Name, [string]$WorkDir, [string]$Command, [string]$LogFile) {
    Write-Host "Starting $Name..." -ForegroundColor Cyan
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', "cd /d `"$WorkDir`" && $Command > `"$LogFile`" 2>&1" -WindowStyle Minimized | Out-Null
}

if ($StartMySql -and -not (Test-ListeningPort 3306)) {
    if (Test-Path 'C:\xampp\mysql_start.bat') {
        Write-Host 'Starting MySQL via XAMPP...' -ForegroundColor Cyan
        Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', 'C:\xampp\mysql_start.bat' -WindowStyle Minimized | Out-Null
        Start-Sleep -Seconds 4
    } else {
        Write-Warning 'MySQL start requested, but C:\xampp\mysql_start.bat was not found.'
    }
}

$phpPath = Resolve-PhpPath
$npmCmd = Resolve-NpmCmd

if (-not (Test-ListeningPort 5000)) {
    Start-BackgroundService -Name 'API' -WorkDir $ApiDir -Command 'node server.js' -LogFile (Join-Path $LogDir 'api.log')
}

if (-not (Test-ListeningPort 3000)) {
    Start-BackgroundService -Name 'Website' -WorkDir $UserDir -Command "`"$npmCmd`" run dev" -LogFile (Join-Path $LogDir 'website.log')
}

if (-not (Test-ListeningPort 8000)) {
    Start-BackgroundService -Name 'Workspace' -WorkDir $WorkspaceDir -Command "`"$phpPath`" -S 127.0.0.1:8000" -LogFile (Join-Path $LogDir 'workspace.log')
}

Write-Host ''
Write-Host 'Electava local stack start requested.' -ForegroundColor Green
Write-Host "Website  : http://127.0.0.1:3000"
Write-Host "API      : http://127.0.0.1:5000"
Write-Host "Workspace: http://127.0.0.1:8000"
Write-Host "Logs     : $LogDir"
