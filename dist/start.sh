#!/usr/bin/env bash
# Nexo FP - script de arranque (Linux / macOS)
# Uso: ./start.sh [puerto]          (por defecto: 8080)
set -euo pipefail

PORT="${1:-${PORT:-8080}}"

# -- Rutas absolutas -----------------------------------------------------------
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA="${ROOT}/data"
APP="${ROOT}/app"
FP="${ROOT}/frankenphp"

# -- macOS: eliminar la cuarentena de Gatekeeper -------------------------------
if [[ "$(uname)" == "Darwin" ]]; then
    xattr -d com.apple.quarantine "${FP}" 2>/dev/null || true
fi

# -- Variables de entorno para la aplicación -----------------------------------
export APP_ENV=prod
export APP_DEBUG=0
export DOCUMENT_ROOT="${APP}/public"
export SERVER_ADDR=":${PORT}"
export DATABASE_URL="sqlite:///${DATA}/nexo-fp.db"
export MIGRATIONS_PATH=migrations/sqlite
export DEFAULT_URI="http://localhost:${PORT}"
export APP_PAGE_SIZE="${APP_PAGE_SIZE:-20}"
export APP_EXTERNAL_ENABLED="${APP_EXTERNAL_ENABLED:-true}"
export APP_EXTERNAL_URL="${APP_EXTERNAL_URL:-https://seneca.juntadeandalucia.es/seneca/jsp/ComprobarUsuarioExt.jsp}"
export APP_EXTERNAL_URL_FORCE_SECURITY="${APP_EXTERNAL_URL_FORCE_SECURITY:-true}"

# -- Carpeta de datos ----------------------------------------------------------
mkdir -p "${DATA}"

# -- APP_SECRET: generar en el primer arranque ---------------------------------
if [[ ! -f "${DATA}/.secret" ]]; then
    echo "Generando APP_SECRET..."
    "${FP}" php-cli -r 'echo bin2hex(random_bytes(32));' 2>/dev/null > "${DATA}/.secret"
fi
export APP_SECRET="$(cat "${DATA}/.secret")"

# -- .env: exponer variables a PHP (bootEnv requiere el fichero .env) ---------
cat > "${APP}/.env" <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
MIGRATIONS_PATH=${MIGRATIONS_PATH}
DEFAULT_URI=${DEFAULT_URI}
APP_PAGE_SIZE=${APP_PAGE_SIZE}
APP_EXTERNAL_ENABLED=${APP_EXTERNAL_ENABLED}
APP_EXTERNAL_URL=${APP_EXTERNAL_URL}
APP_EXTERNAL_URL_FORCE_SECURITY=${APP_EXTERNAL_URL_FORCE_SECURITY}
EOF

# -- Caché: limpiar posibles compilaciones parciales de arranques anteriores ----
rm -rf "${APP}/var/cache/"

# -- Base de datos SQLite ------------------------------------------------------
cd "${APP}"
echo "Precalentando caché. Espere por favor.."
"${FP}" php-cli bin/console cache:warmup --no-interaction

echo "Aplicando migraciones..."
"${FP}" php-cli bin/console doctrine:migrations:migrate --no-interaction

echo "Inicializando datos por defecto..."
"${FP}" php-cli bin/console app:setup --no-interaction || true

# -- Arrancar servidor ---------------------------------------------------------
cd "${ROOT}"
echo ""
echo "  Nexo FP disponible en → http://localhost:${PORT}"
echo "  Pulsa Ctrl+C para detener."
echo ""
exec "${FP}" run --config Caddyfile
