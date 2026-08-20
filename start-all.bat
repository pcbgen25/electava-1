@echo off
setlocal

set "ROOT=%~dp0"
set "API_DIR=%ROOT%API"
set "USER_DIR=%ROOT%user"
set "WORKSPACE_DIR=%ROOT%workspace"
set "PHP_BIN=php"

if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
)

echo ========================================================
echo         ELECTAVA ECOSYSTEM UNIFIED STARTUP SCRIPT       
echo ========================================================
echo.
echo Starting all three systems in separate terminal windows...
echo.

if not exist "%API_DIR%\server.js" (
    echo ERROR: API directory not found at "%API_DIR%"
    pause
    exit /b 1
)

if not exist "%USER_DIR%\package.json" (
    echo ERROR: Frontend directory not found at "%USER_DIR%"
    pause
    exit /b 1
)

if not exist "%WORKSPACE_DIR%\index.php" (
    echo ERROR: Workspace directory not found at "%WORKSPACE_DIR%"
    pause
    exit /b 1
)

:: 1. Start Node.js API
echo [1/3] Launching Node.js API Bridge (Port 5000)...
start "Electava Node API" cmd /k "cd /d ""%API_DIR%"" && title Electava-API && echo Starting Node.js API... && node server.js"

:: 2. Start Next.js frontend
echo [2/3] Launching Next.js User Marketplace (Port 3000)...
start "Electava Next.js Frontend" cmd /k "cd /d ""%USER_DIR%"" && title Electava-Frontend && echo Starting Next.js Frontend... && npm run dev"

:: 3. Start PHP workspace dashboard
echo [3/3] Launching PHP Workspace Dashboard (Port 8000)...
start "Electava PHP Workspace" cmd /k "cd /d ""%WORKSPACE_DIR%"" && title Electava-Workspace && echo Starting PHP Built-in Server... && ""%PHP_BIN%"" -S 127.0.0.1:8000"

echo.
echo ========================================================
echo  All systems have been launched!
echo ========================================================
echo  - User Marketplace : http://localhost:3000
echo  - API Bridge       : http://localhost:5000
echo  - Workspace System : http://localhost:8000
echo.
echo  ** IMPORTANT **
echo  Make sure MySQL is running on port 3306.
echo  If PHP is not on PATH, this script will use C:\xampp\php\php.exe when available.
echo  If the database "electava_workspace" does not exist,
echo  import "install.sql" from the workspace folder.
echo ========================================================
pause
