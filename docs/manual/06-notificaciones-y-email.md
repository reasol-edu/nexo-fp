# Notificaciones por email

La aplicación puede enviar notificaciones automáticas por email en estos casos:

- **Asignación de tutoría:** cuando a un puesto formativo se le asigna un tutor/a dual docente (o se
  cambia el existente), el tutor/a recibe un email con el enlace a la estancia.
- **Nuevos puestos formativos:** al crear puestos en una estancia, los docentes de enlace de la empresa
  reciben un aviso (excepto quien los creó).
- **Recordatorios de firma:** mediante el comando
  [`app:send-reminders`](08-comandos-de-consola.md#appsend-reminders), cada tutor/a dual docente recibe un
  recordatorio de sus puestos pendientes de firma cuando la estancia está próxima a finalizar.
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

## Envío asíncrono

Los emails se envían **en segundo plano** : la verificación de cambio de correo y las notificaciones de
tutoría/firma se encolan y un *worker* las procesa de forma asíncrona, sin penalizar el tiempo de
respuesta. La **recuperación de contraseña** es la excepción y se envía de forma síncrona por ser urgente
(el enlace caduca en 1 hora). Los fallos de envío se registran en el log sin interrumpir nunca la
operación en curso.

El *worker* debe estar en ejecución para que los correos encolados se entreguen:

```bash
php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
```

En los despliegues con **ejecutable binario** no es necesario lanzarlo a mano: los scripts de arranque
(`dist/start.sh`, `dist/start.ps1`, `dist/start.bat`) inician el consumidor junto al servidor y lo
detienen al finalizar. En Windows se recomienda usar `start.ps1` como lanzador.

La gestión de los mensajes fallidos se detalla en
[Operación y mantenimiento](10-operacion-y-mantenimiento.md). Los destinatarios sin dirección de email
registrada se omiten de forma silenciosa.

## Control por niveles

Cada tipo de notificación puede habilitarse o deshabilitarse individualmente desde la sección
**Ajustes**, con tres niveles de granularidad: global, por centro educativo y por docente. El valor más
específico tiene prioridad. Si un docente desactiva las notificaciones desde su perfil, no recibirá
emails independientemente de la configuración global o del centro. Consulta [Ajustes](07-ajustes.md).
