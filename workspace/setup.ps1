# Electava Workspace - Full Test Setup & Testing Tool
# This script configures the environment, downloads dependencies, and generates mock data.

$ErrorActionPreference = "Stop"

Write-Host "--- Electava Workspace Full Test Setup ---" -ForegroundColor Cyan

# 1. Locate PHP and MySQL
$PHP_PATH = "php"
$MYSQL_PATH = "mysql"

try {
    php -v | Out-Null
    mysql --version | Out-Null
} catch {
    Write-Host "PHP or MySQL not found in PATH. Searching in common XAMPP locations..." -ForegroundColor Yellow
    if (Test-Path "C:\xampp\php\php.exe") { $PHP_PATH = "C:\xampp\php\php.exe" }
    if (Test-Path "C:\xampp\mysql\bin\mysql.exe") { $MYSQL_PATH = "C:\xampp\mysql\bin\mysql.exe" }
}

if (!(Get-Command $PHP_PATH -ErrorAction SilentlyContinue) -and !(Test-Path $PHP_PATH)) {
    Write-Host "ERROR: PHP not found. Please install XAMPP or add PHP to your PATH." -ForegroundColor Red
    exit 1
}

# 2. Dependency Management (Downloading Tool)
Write-Host "`n--- [Downloading Dependencies] ---" -ForegroundColor Cyan
if (!(Test-Path "composer.phar")) {
    Write-Host "Composer not found. Downloading composer.phar from getcomposer.org..." -ForegroundColor Gray
    Invoke-WebRequest -Uri "https://getcomposer.org/composer.phar" -OutFile "composer.phar"
}

Write-Host "Installing PHP dependencies (Faker, DotEnv)..." -ForegroundColor Gray
& $PHP_PATH "composer.phar" install --no-dev

# 3. Setup Database
Write-Host "`n--- [Setting up Database] ---" -ForegroundColor Cyan
$DB_NAME = "electava_workspace"
$DB_USER = "root"
$DB_PASS = ""

try {
    # Full clean install if requested (optional)
    # & $MYSQL_PATH -u $DB_USER --password="$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;"
    & $MYSQL_PATH -u $DB_USER --password="$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>$null
    
    Write-Host "Importing schema from install.sql..." -ForegroundColor Gray
    Get-Content "install.sql" | & $MYSQL_PATH -u $DB_USER --password="$DB_PASS" $DB_NAME
    Write-Host "Database schema ready!" -ForegroundColor Green
} catch {
    Write-Host "Warning: Database setup via CLI failed. Ensure MySQL is running." -ForegroundColor Yellow
}

# 4. Generate Mock Data (Full Test)
Write-Host "`n--- [Generating Mock Data] ---" -ForegroundColor Cyan
if (Test-Path "generate_test_data.php") {
    & $PHP_PATH "generate_test_data.php"
}

# 5. Environment Diagnostics
if (Test-Path "check_env.php") {
    Write-Host "`n--- [Running Diagnostics] ---" -ForegroundColor Cyan
    & $PHP_PATH "check_env.php"
}

# 6. Start Server
$PORT = 8000
Write-Host "`n--- [Launching Test Environment] ---" -ForegroundColor Green
Write-Host "Server: http://localhost:$PORT"
Write-Host "Press Ctrl+C to stop."

Start-Process "http://localhost:$PORT"
& $PHP_PATH -S "localhost:$PORT"
