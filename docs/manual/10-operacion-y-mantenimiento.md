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

## Protección de datos (RGPD)

Nexo FP almacena **datos personales** de alumnado (nombre, NIE) y profesorado, y datos de las empresas
colaboradoras. El **centro educativo es el responsable del tratamiento** de esos datos; conviene tenerlo
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
