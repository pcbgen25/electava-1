param(
    [string]$OutputRoot = ''
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
if ($OutputRoot -eq '') {
    $OutputRoot = Join-Path $RepoRoot 'dist'
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$packageRoot = Join-Path $OutputRoot "electava-handoff-$timestamp"

New-Item -ItemType Directory -Force $packageRoot | Out-Null

function Copy-FilteredTree([string]$Source, [string]$Destination, [string[]]$ExcludeDirs, [string[]]$ExcludeFiles) {
    New-Item -ItemType Directory -Force $Destination | Out-Null

    $arguments = @(
        $Source,
        $Destination,
        '/E',
        '/R:1',
        '/W:1',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/NP'
    )

    if ($ExcludeDirs.Count -gt 0) {
        $arguments += '/XD'
        $arguments += $ExcludeDirs
    }

    if ($ExcludeFiles.Count -gt 0) {
        $arguments += '/XF'
        $arguments += $ExcludeFiles
    }

    & robocopy @arguments | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy failed for $Source -> $Destination"
    }
}

$sharedExcludeDirs = @(
    '.git',
    '.next',
    'node_modules',
    '.codex-logs',
    '.codex-local-logs',
    'runtime-logs',
    'logs',
    'dist'
)

$sharedExcludeFiles = @(
    '.env',
    '*.log',
    'error_full.txt',
    'error_output.txt'
)

Copy-FilteredTree -Source $RepoRoot -Destination $packageRoot -ExcludeDirs $sharedExcludeDirs -ExcludeFiles $sharedExcludeFiles

$manifestPath = Join-Path $packageRoot 'HANDOFF_MANIFEST.txt'
@"
Electava handoff package
Generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')

Included modules:
- user/
- API/
- workspace/
- scripts/
- docs and top-level project documentation

Excluded runtime/build directories:
- node_modules
- .next
- logs
- .codex-logs
- .codex-local-logs
- dist
- .env files
"@ | Set-Content -Path $manifestPath -Encoding UTF8

Write-Host "Handoff package created at $packageRoot" -ForegroundColor Green
