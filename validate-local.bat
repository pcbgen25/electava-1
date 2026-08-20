@echo off
setlocal

set "ROOT=%~dp0"
set "SCRIPT=%ROOT%scripts\validate-local.ps1"

if not exist "%SCRIPT%" (
    echo ERROR: validate-local.ps1 not found at "%SCRIPT%"
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" %*
