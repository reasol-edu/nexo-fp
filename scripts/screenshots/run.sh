#!/usr/bin/env bash
# Regenera las capturas de pantalla del manual (docs/manual/img) y de la presentación (docs/slides/img).
#
# Monta un entorno totalmente aislado —SQLite temporal + fixtures de demostración + servidor PHP en el
# puerto 8124— y lanza Playwright contra él. NO toca la base de datos real ni el .env.local.
#
# USO (desde cualquier directorio):
#   scripts/screenshots/run.sh                       # todas las capturas
#   scripts/screenshots/run.sh manual                # solo las del manual
#   scripts/screenshots/run.sh slides                # solo las de la presentación
#   scripts/screenshots/run.sh estancia-compartida   # una o varias por nombre (ver `--list`)
#   scripts/screenshots/run.sh --list                # lista las capturas disponibles
# Variables: PORT (8124), NO_CLEAN=1 (deja el servidor y la base para depurar).
# Requisitos: PHP con pdo_sqlite, Node.js ≥ 18 y conexión a Internet la primera vez (instala Playwright
# y Chromium en var/screenshots/, que está fuera de git).
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ "${1:-}" = "--list" ]; then exec node scripts/screenshots/capture.mjs --list; fi

PORT="${PORT:-8124}"
STATE="var/screenshots"
mkdir -p "$STATE"

if lsof -i "tcp:$PORT" >/dev/null 2>&1; then
  echo "El puerto $PORT está ocupado (¿otro servidor?). Usa PORT=<otro> o libéralo." >&2
  exit 1
fi

# ── Entorno aislado ─────────────────────────────────────────────────────────
# APP_DEBUG=1: con 0 los assets de AssetMapper dan 404 y las páginas salen sin estilos.
export APP_ENV=dev APP_DEBUG=1 APP_LOG=false MAILER_DSN="null://null" APP_EXTERNAL_ENABLED=false
export DATABASE_URL="sqlite:///$(pwd)/$STATE/screenshot.db" MIGRATIONS_PATH=migrations/sqlite

SERVER_PID=""
cleanup() {
  [ -n "${NO_CLEAN:-}" ] && { echo "NO_CLEAN: servidor (pid $SERVER_PID) y $STATE/ conservados."; return; }
  [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
  pkill -f "php.*127.0.0.1:$PORT" 2>/dev/null || true   # los workers de `php -S` sobreviven al proceso padre
  rm -f "$STATE/screenshot.db" "$STATE/server.log"
  rm -rf var/cache/dev
}
trap cleanup EXIT

if [ -d public/assets ]; then
  echo "⚠️  public/assets existe (salida compilada de AssetMapper) y se serviría en lugar de los assets vivos." >&2
  echo "    Bórralo (rm -rf public/assets var/cache/dev) y vuelve a ejecutar." >&2
  exit 1
fi

rm -f "$STATE/screenshot.db"; rm -rf var/cache/dev
echo "▸ Migraciones y fixtures (SQLite aislado)"
php -d memory_limit=1G bin/console doctrine:migrations:migrate --no-interaction >/dev/null
php -d memory_limit=1G bin/console doctrine:fixtures:load --no-interaction --append >/dev/null

echo "▸ Servidor en http://127.0.0.1:$PORT"
PHP_CLI_SERVER_WORKERS=8 php -d memory_limit=1G -d variables_order=EGPCS -S "127.0.0.1:$PORT" -t public/ >"$STATE/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/login" && break; sleep 1; done

# ── Playwright (se instala una sola vez fuera de git) ───────────────────────
if [ ! -d "$STATE/node_modules/playwright" ]; then
  echo "▸ Instalando Playwright en $STATE/"
  (cd "$STATE" && npm init -y >/dev/null 2>&1 && npm install playwright >/dev/null 2>&1)
fi
(cd "$STATE" && npx playwright install chromium >/dev/null 2>&1) || true

echo "▸ Capturando"
BASE="http://127.0.0.1:$PORT" PW_DIR="$(pwd)/$STATE" node scripts/screenshots/capture.mjs "$@"
