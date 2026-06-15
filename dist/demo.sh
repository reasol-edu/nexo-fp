#!/usr/bin/env bash
# Nexo FP - arranque con datos de demostración (Linux / macOS)
# Carga los datos de demostración (fixtures) y arranca la aplicación.
# Uso: ./demo.sh [puerto]          (por defecto: 8080)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

export LOAD_FIXTURES=true
exec "${ROOT}/start.sh" "$@"
