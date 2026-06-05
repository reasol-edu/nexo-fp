#!/bin/sh
set -e

echo "[nexo-fp] Aplicando migraciones de base de datos..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[nexo-fp] Precalentando caché..."
php bin/console cache:warmup --env=prod --no-interaction || true

echo "[nexo-fp] Iniciando FrankenPHP..."
exec "$@"
