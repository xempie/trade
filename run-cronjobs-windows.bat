@echo off
REM Windows Batch Script for Running Cronjobs on XAMPP/Development Environment
REM This script simulates the cronjob behavior for Windows development

echo ========================================
echo Crypto Trading Management - Cronjobs
echo ========================================
echo Starting at: %date% %time%
echo.

REM Set PHP path (adjust if needed)
set PHP_PATH=C:\xampp\php\php.exe

REM Set project path
set PROJECT_PATH=C:\xampp\htdocs\trade

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please update PHP_PATH in this script
    pause
    exit /b 1
)

REM Check if project exists
if not exist "%PROJECT_PATH%" (
    echo ERROR: Project not found at %PROJECT_PATH%
    echo Please update PROJECT_PATH in this script
    pause
    exit /b 1
)

echo PHP Path: %PHP_PATH%
echo Project Path: %PROJECT_PATH%
echo.

REM Create logs directory if it doesn't exist
if not exist "%PROJECT_PATH%\logs" mkdir "%PROJECT_PATH%\logs"

echo Running cronjobs...
echo.

REM Run Price Monitor
echo [%time%] Running Price Monitor...
"%PHP_PATH%" "%PROJECT_PATH%\jobs\price-monitor.php" >> "%PROJECT_PATH%\logs\price-monitor.log" 2>&1
if %ERRORLEVEL% neq 0 (
    echo WARNING: Price Monitor completed with errors
) else (
    echo SUCCESS: Price Monitor completed
)

REM Run Order Status Check
echo [%time%] Running Order Status Check...
"%PHP_PATH%" "%PROJECT_PATH%\jobs\order-status.php" >> "%PROJECT_PATH%\logs\order-status.log" 2>&1
if %ERRORLEVEL% neq 0 (
    echo WARNING: Order Status Check completed with errors
) else (
    echo SUCCESS: Order Status Check completed
)

REM Run Position Sync
echo [%time%] Running Position Sync...
"%PHP_PATH%" "%PROJECT_PATH%\jobs\position-sync.php" >> "%PROJECT_PATH%\logs\position-sync.log" 2>&1
if %ERRORLEVEL% neq 0 (
    echo WARNING: Position Sync completed with errors
) else (
    echo SUCCESS: Position Sync completed
)

REM Run Balance Sync
echo [%time%] Running Balance Sync...
"%PHP_PATH%" "%PROJECT_PATH%\jobs\balance-sync.php" >> "%PROJECT_PATH%\logs\balance-sync.log" 2>&1
if %ERRORLEVEL% neq 0 (
    echo WARNING: Balance Sync completed with errors
) else (
    echo SUCCESS: Balance Sync completed
)

echo.
echo ========================================
echo All cronjobs completed at: %date% %time%
echo ========================================
echo.

REM Show log files summary
echo Log files created:
dir /b "%PROJECT_PATH%\logs\*.log" 2>nul
echo.

echo To view logs:
echo - Price Monitor: type "%PROJECT_PATH%\logs\price-monitor.log"
echo - Order Status: type "%PROJECT_PATH%\logs\order-status.log"
echo - Position Sync: type "%PROJECT_PATH%\logs\position-sync.log"
echo - Balance Sync: type "%PROJECT_PATH%\logs\balance-sync.log"
echo.

REM Optional: Keep window open
echo Press any key to exit...
pause >nul