# Comandos de consola

La aplicación incluye varios comandos para la administración del sistema. Se ejecutan con
`php bin/console <comando>` en desarrollo, o con el binario nativo
(`nexo-fp php-cli bin/console <comando>` en Linux/macOS, `nexo-fp.exe php-cli bin/console <comando>` en
Windows).

## app:setup

Inicializa la aplicación con datos de ejemplo si la base de datos está vacía. Si ya existe algún docente
registrado, el comando no hace nada y muestra un aviso.

**Cuándo usarlo:** primera puesta en marcha en un entorno de desarrollo o pruebas para disponer de un
usuario `admin`/`admin` y un centro educativo de ejemplo listos para usar.

```bash
php bin/console app:setup
```

No acepta argumentos ni opciones. Es idempotente: se puede ejecutar varias veces sin riesgo.

## app:create-educational-centre

Crea un nuevo centro educativo y su primer curso académico (el curso actual, calculado automáticamente).

```bash
php bin/console app:create-educational-centre [<código>] [<nombre>] [<ciudad>]
```

| Argumento | Descripción | Requisito |
|-----------|-------------|-----------|
| `código` | Código del centro (p. ej. `23700281`) | Se solicita de forma interactiva si no se indica |
| `nombre` | Nombre del centro (p. ej. `IES Oretania`) | Se solicita de forma interactiva si no se indica |
| `ciudad` | Ciudad del centro (p. ej. `Linares`) | Se solicita de forma interactiva si no se indica |

El comando falla si ya existe un centro con el mismo código.

## app:create-admin

Crea un docente con privilegios de administrador global.

```bash
php bin/console app:create-admin <nombre_de_usuario> [<contraseña>]
```

| Argumento | Descripción | Requisito |
|-----------|-------------|-----------|
| `nombre_de_usuario` | Nombre de usuario para el login | **Obligatorio** |
| `contraseña` | Contraseña en texto plano | Se solicita de forma oculta e interactiva si no se indica |

El comando falla si el nombre de usuario ya está registrado. La contraseña se almacena siempre hasheada.

## app:send-reminders

Envía un recordatorio por email a cada tutor/a dual docente con puestos formativos pendientes de firma en
estancias que finalizan exactamente dentro de N días.

```bash
php bin/console app:send-reminders [--days=N]
```

| Opción | Descripción | Valor por defecto |
|--------|-------------|-------------------|
| `--days` | Días que faltan para el fin de la estancia | `7` |

Requiere tener configurado el envío de correo (`MAILER_DSN` y `MAILER_FROM`) y `DEFAULT_URI` con la URL
pública de la aplicación para que los enlaces de los emails funcionen. Los puestos cuyo tutor no tiene
email registrado se omiten con un aviso.

Está pensado para ejecutarse una vez al día mediante cron; al filtrar por la fecha exacta de fin, cada
puesto recibe un único recordatorio:

```cron
# Todos los días a las 8:00
0 8 * * * cd /ruta/a/nexo-fp && php bin/console app:send-reminders --days=7
```
