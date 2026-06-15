#!/usr/bin/env bash
# Nexo FP - arranque con datos de demostración (macOS, doble clic)
# Pensado para abrirse con doble clic en el Finder: arranca la aplicación con
# los datos de demostración ya cargados. Equivale a ejecutar ./demo.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT}"
exec "${ROOT}/demo.sh"
