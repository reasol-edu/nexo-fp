#!/usr/bin/env bash
# Pruebas de navegador (Playwright) del JavaScript de la aplicación y auditoría de accesibilidad (axe-core).
#
# Reutiliza el entorno aislado de las capturas (SQLite temporal + fixtures + servidor PHP en el puerto 8124),
# así que NO toca la base de datos real ni el .env.local. Ver scripts/screenshots/run.sh para los requisitos.
#
# USO (desde cualquier directorio):
#   scripts/e2e/run.sh                    # todas las pruebas
#   scripts/e2e/run.sh --only=a11y        # solo las que contengan ese texto en su nombre
# Variables: PORT (8124), NO_CLEAN=1 (deja el servidor y la base para depurar).
set -euo pipefail
cd "$(dirname "$0")/../.."
HARNESS_SCRIPT=scripts/e2e/e2e.mjs exec scripts/screenshots/run.sh "$@"
