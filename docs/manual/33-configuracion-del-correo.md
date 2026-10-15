# Configuración del correo

Nexo FP envía avisos automáticos por correo (los que recibe cada docente se describen en
[Avisos por correo](14-avisos-por-correo.md)). Este capítulo explica cómo **conectar la aplicación a un
servidor de correo** y cómo funciona el envío en segundo plano.

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

!!! warning "Contraseña de aplicación de Google"
    Necesitas una **contraseña de aplicación**, no la contraseña normal de la cuenta. Google no permite
    autenticarse por SMTP con la contraseña habitual; hay que generar una contraseña de aplicación
    específica, y para ello la cuenta **debe tener activada la verificación en dos pasos**.

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

!!! note "Límites de envío de Gmail"
    Orientativos: ~500 correos/día en cuentas gratuitas y ~2000/día en Google Workspace.
    Para volúmenes mayores, usa un servicio SMTP transaccional.

## Envío asíncrono {#envio-asincrono}

Los emails se envían **en segundo plano**: la verificación de cambio de correo y las notificaciones de
tutoría/firma se encolan y un *worker* las procesa de forma asíncrona, sin penalizar el tiempo de
respuesta. La **recuperación de contraseña** es la excepción y se envía de forma síncrona por ser urgente
(el enlace caduca en 1 hora). Los fallos de envío se registran en el log sin interrumpir nunca la
operación en curso.

El *worker* debe estar en ejecución para que los correos encolados se entreguen y para que se disparen
los avisos programados (recordatorio diario de firma, resumen semanal y envío horario de los resúmenes
diarios). Consume dos transportes: `async` (los correos) y `scheduler_default` (la programación
automática):

```bash
php bin/console messenger:consume async scheduler_default --time-limit=3600 --memory-limit=128M
```

Los avisos programados usan el componente **Symfony Scheduler** (recordatorio de firma a las 8:00, resumen
semanal los lunes a las 8:00 y comprobación de resúmenes diarios cada hora) y los ejecuta este mismo
*worker*, por lo que **no hace falta configurar un cron externo** del sistema operativo. La programación es
*stateful*: si el *worker* estuvo apagado a la hora del disparo, recupera la última ejecución pendiente al
arrancar, y el control de idempotencia evita reenvíos.

No es necesario lanzarlo a mano en los despliegues estándar:

- Con **Docker Compose**, el `compose.yaml` incluye un servicio `worker` dedicado que ejecuta el
  consumidor de forma continua y se reinicia automáticamente.
- Con **ejecutable binario**, los scripts de arranque (`dist/start.sh`, `dist/start.ps1`,
  `dist/start.bat`) inician el consumidor junto al servidor y lo detienen al finalizar. En Windows se
  recomienda usar `start.ps1` como lanzador.

La gestión de los mensajes fallidos se detalla en
[Operación y mantenimiento](34-operacion-y-mantenimiento.md#correos-en-cola-messenger). Los destinatarios
sin dirección de email registrada se omiten de forma silenciosa.

## Resúmenes diarios

Quien active el resumen diario recibe sus avisos agrupados a la hora que elija (7:00 por defecto, **hora del
servidor**). El *worker* comprueba cada hora qué resúmenes han vencido. Si el envío falla, las líneas se
reintentan en la hora siguiente; las que llevan más de 14 días sin poder enviarse se descartan. También
se puede lanzar a mano con [`app:send-daily-digests`](35-comandos-de-consola.md#appsend-daily-digests).
Cómo se activa y qué contiene se explica en [Avisos por correo](14-avisos-por-correo.md) y en
[Control y registro de correos](26-control-y-registro-de-correos.md).
