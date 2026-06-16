# Notificaciones por email

La aplicación puede enviar notificaciones automáticas por email en estos casos:

- **Asignación de tutoría:** cuando a un puesto formativo se le asigna un tutor/a dual docente (o se
  cambia el existente), el tutor/a recibe un email con el enlace a la estancia.
- **Nuevos puestos formativos:** al crear puestos en una estancia, los docentes de enlace de la empresa
  reciben un aviso (excepto quien los creó).
- **Recordatorios de firma:** aviso **diario** por cada puesto formativo en estado «Registrado en Séneca»
  que aún no esté firmado y cuya estancia **comience** dentro de los próximos *X* días (configurable, 7 por
  defecto). Se envía a todas las personas con responsabilidad sobre el puesto —el **tutor/a dual docente**,
  la **coordinación de FP dual** de la enseñanza y la **jefatura de la familia profesional**—, con un único
  correo por persona que agrupa las estancias y, en cada una, sus estudiantes (centro de trabajo y tutores
  asignados). Quien es solo tutor/a ve únicamente sus estudiantes; coordinación y jefatura ven todos los de
  su área de responsabilidad. Se sigue avisando cada día hasta que el puesto se firma, incluso si la
  estancia ya ha comenzado. El envío es **automático** (sin cron externo); también puede lanzarse a mano con
  [`app:send-reminders`](08-comandos-de-consola.md#appsend-reminders).
- **Verificación de cambio de email:** cuando un docente no administrador cambia su dirección de correo,
  recibe un email en el nuevo buzón con un enlace de verificación válido 24 horas. El cambio no tiene
  efecto hasta que se confirma; el email anterior sigue activo durante ese periodo.
- **Recuperación de contraseña:** cuando un docente con acceso local solicita recuperar su contraseña,
  recibe un enlace válido 1 hora en el correo que tiene registrado en su cuenta. Los usuarios con acceso
  externo (Séneca/IdEA) no pueden usar este flujo.

## Activar el correo

Las notificaciones están **desactivadas por defecto** (`MAILER_DSN=null://null`). Para activarlas,
configura en el entorno:

```dotenv
# Transporte SMTP (u otro soportado por symfony/mailer)
MAILER_DSN=smtp://usuario:clave@servidor:587
# Dirección remitente de los emails automáticos
MAILER_FROM=no-responder@tudominio.es
# URL pública de la aplicación, usada en los enlaces de los emails
DEFAULT_URI=https://nexo.tudominio.es
```

### Usar una cuenta de Gmail

Nexo FP incluye el transporte de Gmail, así que puedes enviar los correos a través de una cuenta de
**Gmail** o **Google Workspace** con el esquema `gmail://`:

```dotenv
MAILER_DSN=gmail://USUARIO:CONTRASEÑA_DE_APLICACION@default
MAILER_FROM=tu-cuenta@gmail.com
```

> ⚠️ **Necesitas una «contraseña de aplicación», no la contraseña normal de la cuenta.** Google no permite
> autenticarse por SMTP con la contraseña habitual; hay que generar una contraseña de aplicación
> específica, y para ello la cuenta **debe tener activada la verificación en dos pasos**.

Pasos para obtenerla:

1. Activa la **verificación en dos pasos** en la cuenta de Google (en **Seguridad**), si no la tienes ya.
2. Entra en **Contraseñas de aplicaciones** (<https://myaccount.google.com/apppasswords>), ponle un nombre
   (por ejemplo, «Nexo FP») y genera una.
3. Google muestra una clave de **16 caracteres**. Cópiala **sin los espacios** y úsala como
   `CONTRASEÑA_DE_APLICACION` en el DSN.

Notas sobre el DSN:

- `USUARIO` es tu dirección de Gmail. Como el carácter `@` separa las partes del DSN, si incluyes el
  dominio debes escribirlo codificado como `%40` (por ejemplo, `tu-cuenta%40gmail.com`). En cuentas
  `@gmail.com` basta con la parte anterior a `@gmail.com`.
- Si la contraseña de aplicación contiene caracteres especiales, codifícalos igualmente (por ejemplo, `@`
  → `%40`). Las contraseñas de aplicación de Google solo llevan letras, así que normalmente no hace falta.
- Gmail reescribe el remitente a la cuenta autenticada, por lo que conviene que `MAILER_FROM` coincida con
  esa dirección.

> Ten en cuenta los **límites de envío** de Google (orientativos: ~500 correos/día en cuentas gratuitas y
> ~2000/día en Google Workspace). Para volúmenes mayores, usa un servicio SMTP transaccional.

## Envío asíncrono

Los emails se envían **en segundo plano**: la verificación de cambio de correo y las notificaciones de
tutoría/firma se encolan y un *worker* las procesa de forma asíncrona, sin penalizar el tiempo de
respuesta. La **recuperación de contraseña** es la excepción y se envía de forma síncrona por ser urgente
(el enlace caduca en 1 hora). Los fallos de envío se registran en el log sin interrumpir nunca la
operación en curso.

El *worker* debe estar en ejecución para que los correos encolados se entreguen y para que se dispare el
aviso diario de firma. Consume dos transportes: `async` (los correos) y `scheduler_default` (la
programación automática del recordatorio):

```bash
php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
```

El recordatorio de firma se programa con el componente **Symfony Scheduler** (disparo diario a las 8:00) y
lo ejecuta este mismo *worker*, por lo que **no hace falta configurar un cron externo** del sistema
operativo. La programación es *stateful*: si el *worker* estuvo apagado a la hora del disparo, recupera la
última ejecución pendiente al arrancar, y el control de idempotencia diaria evita reenvíos.

No es necesario lanzarlo a mano en los despliegues estándar:

- Con **Docker Compose**, el `compose.yaml` incluye un servicio `worker` dedicado que ejecuta el
  consumidor de forma continua y se reinicia automáticamente.
- Con **ejecutable binario**, los scripts de arranque (`dist/start.sh`, `dist/start.ps1`,
  `dist/start.bat`) inician el consumidor junto al servidor y lo detienen al finalizar. En Windows se
  recomienda usar `start.ps1` como lanzador.

La gestión de los mensajes fallidos se detalla en
[Operación y mantenimiento](10-operacion-y-mantenimiento.md). Los destinatarios sin dirección de email
registrada se omiten de forma silenciosa.

## Control por niveles

Cada tipo de notificación puede habilitarse o deshabilitarse individualmente desde la sección
**Ajustes**, con tres niveles de granularidad: global, por centro educativo y por docente. El valor más
específico tiene prioridad. Si un docente desactiva las notificaciones desde su perfil, no recibirá
emails independientemente de la configuración global o del centro. Consulta [Ajustes](07-ajustes.md).
