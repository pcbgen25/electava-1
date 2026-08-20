param(
    [switch]$IncludeMySql
)

$ErrorActionPreference = 'Stop'

$ports = @(3000, 5000, 8000)
if ($IncludeMySql) {
    $ports += 3306
}

function Stop-PortProcess([int]$Port) {
    $connections = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -ExpandProperty OwningProcess -Unique

    foreach ($pid in $connections) {
        if ($pid -and $pid -gt 0) {
            try {
                Stop-Process -Id $pid -Force -ErrorAction Stop
                Write-Host "Stopped process $pid on port $Port" -ForegroundColor Yellow
            } catch {
                Write-Warning "Unable to stop process $pid on port $Port: $($_.Exception.Message)"
            }
        }
    }
}

foreach ($port in $ports) {
    Stop-PortProcess -Port $port
}

Write-Host 'Electava local stop sequence completed.' -ForegroundColor Green
