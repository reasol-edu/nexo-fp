#!/bin/sh
set -e

echo "[nexo-fp] Aplicando migraciones de base de datos..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "[nexo-fp] Inicializando datos por defecto..."
php bin/console app:setup --no-interaction --env=prod

if [ "${LOAD_FIXTURES:-false}" = "true" ]; then
    echo "[nexo-fp] Cargando datos de demostración..."
    php bin/console doctrine:fixtures:load --no-interaction --append --env=prod
fi

echo "[nexo-fp] Precalentando caché..."
php bin/console cache:warmup --env=prod --no-interaction || true

echo "[nexo-fp] Iniciando FrankenPHP..."
exec "$@"
