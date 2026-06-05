#!/bin/sh
set -e

echo "[nexo-fp] Actualizando esquema de base de datos..."
php bin/console doctrine:schema:update --force --env=prod --no-interaction

echo "[nexo-fp] Limpiando caché..."
php bin/console cache:clear --env=prod --no-interaction || true

echo "[nexo-fp] Precalentando caché..."
php bin/console cache:warmup --env=prod --no-interaction || true

echo "[nexo-fp] Iniciando FrankenPHP..."
exec "$@"
