@echo off
setlocal

set "ROOT=%~dp0"
set "SCRIPT=%ROOT%scripts\package-handoff.ps1"

if not exist "%SCRIPT%" (
    echo ERROR: package-handoff.ps1 not found at "%SCRIPT%"
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" %*
