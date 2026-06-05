#Requires -Version 5.1
# Nexo FP — script de arranque (Windows PowerShell)
# Uso: .\start.ps1 [-Port 8080]
param(
    [int]$Port = $(if ($env:PORT) { [int]$env:PORT } else { 8080 })
)

$ErrorActionPreference = "Stop"

# ── Rutas ─────────────────────────────────────────────────────────────────────
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$Data = Join-Path $Root "data"
$App  = Join-Path $Root "app"
$FP   = Join-Path $Root "frankenphp.exe"

# ── Variables de entorno ───────────────────────────────────────────────────────
$env:APP_ENV       = "prod"
$env:APP_DEBUG     = "0"
$env:DOCUMENT_ROOT = Join-Path $App "public"
$env:SERVER_ADDR   = ":$Port"

# SQLite necesita barras hacia delante en la URL
$dataFwd = $Data -replace "\\", "/"
$env:DATABASE_URL  = "sqlite:///$dataFwd/nexo-fp.db"

$env:DEFAULT_URI = "http://localhost:$Port"
if (-not $env:APP_PAGE_SIZE)               { $env:APP_PAGE_SIZE = "20" }
if (-not $env:APP_EXTERNAL_ENABLED)        { $env:APP_EXTERNAL_ENABLED = "true" }
if (-not $env:APP_EXTERNAL_URL)            { $env:APP_EXTERNAL_URL = "https://seneca.juntadeandalucia.es/seneca/jsp/ComprobarUsuarioExt.jsp" }
if (-not $env:APP_EXTERNAL_URL_FORCE_SECURITY) { $env:APP_EXTERNAL_URL_FORCE_SECURITY = "true" }

# ── Carpeta de datos ───────────────────────────────────────────────────────────
New-Item -ItemType Directory -Force -Path $Data | Out-Null

# ── APP_SECRET: generar en el primer arranque ─────────────────────────────────
$SecretFile = Join-Path $Data ".secret"
if (-not (Test-Path $SecretFile)) {
    Write-Host "Generando APP_SECRET..."
    $secret = & $FP php-cli -r 'echo bin2hex(random_bytes(32));' 2>$null
    $secret | Set-Content -Path $SecretFile -NoNewline -Encoding ascii
}
$env:APP_SECRET = (Get-Content $SecretFile -Raw -Encoding ascii).Trim()

# ── Base de datos SQLite ───────────────────────────────────────────────────────
Push-Location $App
try {
    $dbFile = Join-Path $Data "nexo-fp.db"
    if (-not (Test-Path $dbFile)) {
        Write-Host "Creando esquema de base de datos..."
        & $FP php-cli bin/console doctrine:schema:create --env=prod --no-interaction
    } else {
        Write-Host "Actualizando esquema de base de datos..."
        & $FP php-cli bin/console doctrine:schema:update --force --env=prod --no-interaction 2>$null
    }

    # ── Caché de Symfony ──────────────────────────────────────────────────────
    Write-Host "Precalentando caché..."
    & $FP php-cli bin/console cache:warmup --env=prod --no-interaction 2>$null
} finally {
    Pop-Location
}

# ── Arrancar servidor ──────────────────────────────────────────────────────────
Set-Location $Root
Write-Host ""
Write-Host "  Nexo FP disponible en -> http://localhost:$Port"
Write-Host "  Pulsa Ctrl+C para detener."
Write-Host ""
& $FP run --config Caddyfile
