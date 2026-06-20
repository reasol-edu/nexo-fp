# Operación y mantenimiento

Tareas habituales para mantener una instalación de Nexo FP en marcha.

## Copias de seguridad

- **Binario nativo (SQLite):** todo lo generado en tiempo de ejecución se guarda en el directorio
  `data/` del paquete. Para hacer una copia de seguridad basta con **copiar ese directorio** (incluye la
  base de datos `data/nexo-fp.db` y el secreto `data/.secret`).
- **Docker (PostgreSQL):** la base de datos está en `./data/postgres/`. Para una copia consistente, usa
  `pg_dump` sobre el contenedor de la base de datos (servicio `database`, con el usuario y la base
  `nexo` que define `compose.yaml`):
  ```bash
  docker compose exec -T database pg_dump -U nexo nexo > backup.sql
  ```
  Para restaurarla en una instalación limpia:
  ```bash
  docker compose exec -T database psql -U nexo nexo < backup.sql
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

El *worker* que procesa la cola debe estar en ejecución. Consume el transporte de correos (`async`) y el
de la programación automática (`scheduler_default`):

```bash
php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
```

En los despliegues con **Docker Compose** (servicio `worker`) y con **binario nativo** (scripts de
arranque) el consumidor se lanza y se mantiene automáticamente; el comando anterior solo es necesario en
ejecuciones manuales o de desarrollo.

## Sincronización en vivo (Mercure)

La actualización en vivo de la pantalla de estancia se apoya en un **hub de Mercure embebido en
FrankenPHP**, tanto en Docker como en el binario nativo. No es un servicio aparte que haya que
arrancar, supervisar ni respaldar: vive dentro del mismo proceso del servidor de aplicaciones.
En despliegues con **Plesk** (PHP-FPM estándar) esta función no está disponible; la aplicación
funciona con normalidad sin ella.

Lo único que persiste es el secreto que firma los avisos: en el binario nativo se genera en el primer
arranque y se guarda en `data/.mercure_secret` (incluido, por tanto, en la copia de seguridad del
directorio `data/`); en Docker es la variable `MERCURE_JWT_SECRET`. Consulta los detalles y la
resolución de problemas en [Sincronización en vivo](09-despliegue.md#sincronizacion-en-vivo-mercure) y
[Resolución de problemas](11-resolucion-de-problemas.md#la-pantalla-de-estancia-no-se-actualiza-sola).

## Recordatorios de firma

El aviso diario de puestos registrados sin firmar se **programa automáticamente** con Symfony Scheduler y
lo dispara el *worker* anterior (transporte `scheduler_default`) una vez al día a las 8:00. **No es
necesario configurar cron**: basta con que el *worker* esté en marcha. La programación es *stateful*, así
que recupera el último disparo perdido si el *worker* estuvo detenido, y el control de idempotencia diaria
evita reenvíos.

Si se prefiere un disparo externo (o para una ejecución puntual), puede lanzarse el comando
[`app:send-reminders`](08-comandos-de-consola.md#appsend-reminders) manualmente o desde cron:

```cron
# Alternativa al Scheduler: todos los días a las 8:00
0 8 * * * cd /ruta/a/nexo-fp && php bin/console app:send-reminders
```

## Actualización

- **Docker:** `docker compose pull` (o `build`) y `docker compose up -d`. Las migraciones se aplican
  solas al arrancar.
- **Binario nativo:** descarga el nuevo paquete y conserva el directorio `data/` de la instalación
  anterior; al arrancar, aplicará las migraciones pendientes sobre la base de datos existente.
- **Ubuntu Server 26.04:** descarga el nuevo paquete y extráelo sobre `/opt/nexo-fp/`; el directorio
  `data/` y el fichero `.env.local` se conservan al no estar incluidos en el paquete. Las migraciones
  se aplican automáticamente al reiniciar los servicios:
  ```bash
  sudo systemctl stop nexo-fp-worker nexo-fp
  sudo -u nexofp tar xzf nexo-fp-X.Y.Z-linux-x86_64.tar.gz -C /opt/nexo-fp --strip-components=1
  sudo systemctl start nexo-fp nexo-fp-worker
  ```
- **Plesk:** `git pull` (o sube los archivos nuevos), después por SSH:
  ```bash
  composer install --no-dev --optimize-autoloader
  php bin/console doctrine:migrations:migrate --no-interaction
  php bin/console cache:clear
  ```

## Protección de datos (RGPD)

Nexo FP almacena **datos personales** de alumnado (nombre, NIE) y profesorado, así como información de
las empresas colaboradoras. El **centro educativo es el responsable del tratamiento** de esos datos; conviene tenerlo
en cuenta al operar la aplicación:

- **Acceso mínimo necesario.** Los permisos están diseñados para que cada docente vea solo los datos que
  necesita (consulta [Roles y permisos](03-roles-y-permisos.md)). Revisa periódicamente quién tiene rol
  de administrador global o de centro.
- **Copias de seguridad.** Las copias contienen datos personales: guárdalas en un lugar seguro, cifradas
  si es posible, y elimínalas cuando dejen de ser necesarias.
- **Conservación y borrado.** Da de baja al alumnado y elimina los cursos académicos que ya no deban
  conservarse según la política de tu centro. Al desmontar una instalación, borra el directorio `data/`
  (binario) o el volumen `./data/postgres/` (Docker) para no dejar datos residuales.
- **Comunicaciones.** Los emails automáticos incluyen enlaces a la aplicación y nombres de personas;
  asegúrate de configurar un remitente y un servidor de correo del propio centro
  (consulta [Notificaciones por email](06-notificaciones-y-email.md)).
- **Acceso por red.** En instalaciones accesibles desde Internet, usa siempre **HTTPS** y contraseñas
  robustas; nunca dejes el usuario `admin` / `admin` por defecto.
