@echo off
:: Nexo FP — script de arranque (Windows)
:: Uso: start.bat [puerto]          (por defecto: 8080)
setlocal enabledelayedexpansion

if "%~1"=="" ( set PORT=8080 ) else ( set PORT=%~1 )
if not "%PORT_ENV%"=="" set PORT=%PORT_ENV%

:: ── Rutas ──────────────────────────────────────────────────────────────────
set "ROOT=%~dp0"
set "ROOT=%ROOT:~0,-1%"
set "DATA=%ROOT%\data"
set "APP=%ROOT%\app"
set "FP=%ROOT%\frankenphp.exe"

:: ── Variables de entorno ────────────────────────────────────────────────────
set APP_ENV=prod
set APP_DEBUG=0
set DOCUMENT_ROOT=%APP%\public
set SERVER_ADDR=:%PORT%

:: SQLite necesita barras hacia delante en la URL
set "DATA_FWD=%DATA:\=/%"
set DATABASE_URL=sqlite:///%DATA_FWD%/nexo-fp.db

set MIGRATIONS_PATH=migrations/sqlite
set DEFAULT_URI=http://localhost:%PORT%
if "%APP_PAGE_SIZE%"==""               set APP_PAGE_SIZE=20
if "%APP_EXTERNAL_ENABLED%"==""        set APP_EXTERNAL_ENABLED=true
if "%APP_EXTERNAL_URL%"==""            set APP_EXTERNAL_URL=https://seneca.juntadeandalucia.es/seneca/jsp/ComprobarUsuarioExt.jsp
if "%APP_EXTERNAL_URL_FORCE_SECURITY%"=="" set APP_EXTERNAL_URL_FORCE_SECURITY=true

:: ── Carpeta de datos ────────────────────────────────────────────────────────
if not exist "%DATA%" mkdir "%DATA%"

:: ── APP_SECRET: generar en el primer arranque ────────────────────────────────
if not exist "%DATA%\.secret" (
    echo Generando APP_SECRET...
    for /f "delims=" %%i in ('"%FP%" php-cli -r "echo bin2hex(random_bytes(32));" 2^>nul') do (
        echo %%i> "%DATA%\.secret"
    )
)
set /p APP_SECRET=<"%DATA%\.secret"
:: Eliminar posible retorno de carro
set APP_SECRET=%APP_SECRET: =%

:: ── Base de datos SQLite ─────────────────────────────────────────────────────
cd /d "%APP%"
echo Aplicando migraciones...
"%FP%" php-cli bin\console doctrine:migrations:migrate --no-interaction 2>nul

:: ── Caché de Symfony ─────────────────────────────────────────────────────────
echo Precalentando cache...
"%FP%" php-cli bin\console cache:warmup --env=prod --no-interaction 2>nul

:: ── Arrancar servidor ────────────────────────────────────────────────────────
cd /d "%ROOT%"
echo.
echo   Nexo FP disponible en -^> http://localhost:%PORT%
echo   Pulsa Ctrl+C para detener.
echo.
"%FP%" run --config Caddyfile
