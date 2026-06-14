# Operación y mantenimiento

Tareas habituales para mantener una instalación de Nexo FP en marcha.

## Copias de seguridad

- **Binario nativo (SQLite):** todo lo generado en tiempo de ejecución se guarda en el directorio
  `data/` del paquete. Para hacer una copia de seguridad basta con **copiar ese directorio** (incluye la
  base de datos `data/nexo-fp.db` y el secreto `data/.secret`).
- **Docker (PostgreSQL):** la base de datos está en `./data/postgres/`. Para una copia consistente, usa
  `pg_dump` sobre el contenedor:
  ```bash
  docker compose exec app pg_dump ... > backup.sql
  ```

## Correos en cola (Messenger)

Los emails se entregan de forma asíncrona (consulta
[Notificaciones por email](06-notificaciones-y-email.md)). Si un envío falla, se reintenta hasta 3 veces;
agotados los reintentos, el mensaje pasa al transporte `failed`. Para inspeccionarlos y gestionarlos:

```bash
php bin/console messenger:failed:show              # listar mensajes fallidos
php bin/console messenger:failed:retry             # reintentar (interactivo)
php bin/console messenger:failed:remove <id>       # descartar un mensaje
```

El *worker* que procesa la cola debe estar en ejecución:

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

En el despliegue con binario nativo, los scripts de arranque lo lanzan y detienen automáticamente.

## Recordatorios de firma

El comando [`app:send-reminders`](08-comandos-de-consola.md#appsend-reminders) avisa a los tutores de los
puestos pendientes de firma. Está pensado para programarse con cron, una vez al día:

```cron
# Todos los días a las 8:00
0 8 * * * cd /ruta/a/nexo-fp && php bin/console app:send-reminders --days=7
```

## Actualización

- **Docker:** `docker compose pull` (o `build`) y `docker compose up -d`. Las migraciones se aplican
  solas al arrancar.
- **Binario nativo:** descarga el nuevo paquete y conserva el directorio `data/` de la instalación
  anterior; al arrancar, aplicará las migraciones pendientes sobre la base de datos existente.
