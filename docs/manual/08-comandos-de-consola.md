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

Envía el aviso diario de puestos formativos «Registrados en Séneca» sin firmar cuyas estancias comienzan
dentro de los próximos días, a las personas con responsabilidad sobre cada puesto (tutor/a dual docente,
coordinación de FP dual y jefatura de la familia profesional). Es el mismo trabajo que se ejecuta
automáticamente cada día; este comando permite lanzarlo a mano.

```bash
php bin/console app:send-reminders [--days=N]
```

| Opción | Descripción | Valor por defecto |
|--------|-------------|-------------------|
| `--days` | Sobre-escribe los días de antelación en todos los centros | Ajuste `email.notification.signature_reminder.days` por centro (7 si no está definido) |

Sin `--days`, cada centro usa su propio valor del ajuste
[`email.notification.signature_reminder.days`](07-ajustes.md). Con `--days` se fuerza ese número de días
para todos los centros (debe ser un entero ≥ 1).

Requiere tener configurado el envío de correo (`MAILER_DSN` y `MAILER_FROM`) y `DEFAULT_URI` con la URL
pública de la aplicación para que los enlaces de los emails funcionen.

!!! tip "Sin configuración de cron"
    El recordatorio se programa con Symfony Scheduler y lo dispara el *worker* de Messenger una vez al
    día (consulta [Notificaciones por email](06-notificaciones-y-email.md)). El comando queda como
    complemento para ejecución manual o puntual.
