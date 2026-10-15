# Resolución de problemas

Soluciones a los problemas técnicos más habituales de una instalación de Nexo FP: arranque, acceso del
administrador, correo y sincronización en vivo. Si no encuentras tu caso, revisa también
[Operación y mantenimiento](34-operacion-y-mantenimiento.md).

Los problemas que se resuelven desde la aplicación tienen sus propios capítulos:
[Problemas de uso](16-problemas-de-uso.md) (profesorado) y
[Problemas de administración](27-problemas-de-administracion.md) (importaciones, permisos y ajustes).

## No puedo arrancar la aplicación

### «El puerto ya está en uso» / la página no carga

Otra aplicación está ocupando el puerto. Arranca Nexo FP en otro puerto:

```bash
./start.sh 9000          # Linux / macOS (o ./demo.sh 9000)
start.bat 9000           # Windows CMD
.\start.ps1 -Port 9000   # Windows PowerShell
```

En Docker, cambia `HTTP_PORT` (y `HTTPS_PORT`) en tu `.env.local`.

### macOS bloquea el binario («desarrollador no identificado»)

Es el aviso de Gatekeeper porque la aplicación no está firmada con un certificado de Apple. Es normal y
se resuelve en un par de clics: sigue las instrucciones de
[Prueba rápida → aviso de seguridad en macOS](32-despliegue.md#prueba-rapida-en-tu-ordenador-sin-conocimientos-tecnicos).

### Windows bloquea el binario (SmartScreen o antivirus)

Al ser un ejecutable descargado y sin firma comercial, Windows SmartScreen puede mostrar «Windows
protegió tu PC». Pulsa **Más información → Ejecutar de todas formas**. Si un antivirus lo pone en
cuarentena, añade una excepción para la carpeta `nexo-fp`.

## No puedo entrar

### He olvidado la contraseña de administrador

Crea un administrador nuevo desde la línea de comandos (no se puede recuperar la contraseña del usuario
`admin` original, pero sí dar de alta otro administrador):

```bash
php bin/console app:create-admin <nuevo_usuario>
```

En el binario nativo: `nexo-fp php-cli bin/console app:create-admin <nuevo_usuario>`. Consulta
[`app:create-admin`](35-comandos-de-consola.md#appcreate-admin).

### El navegador dice «la conexión no es privada» en desarrollo

El servidor de desarrollo (`symfony server:start`) usa un certificado **autofirmado** en
`https://localhost:8000`. Es lo esperado: acepta la excepción de seguridad del navegador, o usa
`http://localhost:8000` arrancando con `php -S`.

## No funciona el correo

### No llegan los emails

1. Comprueba que el correo está **activado**: `MAILER_DSN` debe apuntar a un servidor SMTP real (por
   defecto es `null://null`, que descarta los mensajes). Ver
   [Configuración del correo](33-configuracion-del-correo.md).
2. El *worker* de la cola debe estar en marcha (con Docker Compose y con el binario nativo se lanza
   solo). Revisa los [mensajes fallidos](34-operacion-y-mantenimiento.md#correos-en-cola-messenger).
3. Comprueba que la notificación está habilitada en [Ajustes](25-ajustes-y-bloqueos.md) (global, centro y
   personal) y el [registro de correos](26-control-y-registro-de-correos.md#registro-de-correos-enviados)
   para ver si el envío aparece como **Fallido** y con qué motivo.
4. Los destinatarios **sin dirección de email** registrada se omiten de forma silenciosa.
5. Si el usuario tiene activado el **resumen diario**, sus avisos llegan una vez al día a la hora
   configurada, y solo mientras el *worker* esté en marcha (el envío lo dispara cada hora). Puedes forzarlo
   con [`app:send-daily-digests --now`](35-comandos-de-consola.md#appsend-daily-digests).

## La pantalla de estancia no se actualiza sola

La [sincronización en vivo](32-despliegue.md#sincronizacion-en-vivo-mercure) puede quedar inactiva por
varios motivos. La aplicación sigue funcionando con normalidad; solo hay que recargar para ver los
cambios de otras personas.

1. Comprueba el **modo de despliegue**: la sincronización en vivo necesita el hub embebido en FrankenPHP
   (Docker o binario nativo). En desarrollo con `symfony server:start`, el hub lo aporta el overlay
   `compose.dev.yaml` (contenedor `dunglas/mercure`) y el Symfony CLI lo detecta automáticamente. El servidor de
   desarrollo `php -S` no tiene hub ni inyección de variables, así que no hay tiempo real.
2. En **Docker**, asegúrate de que `MERCURE_JWT_SECRET` está definida; sin ella el contenedor no arranca.
3. Si hay un **proxy inverso** delante (Nginx, Apache, balanceador), debe permitir conexiones
   *Server-Sent Events* (SSE) sin almacenarlas en búfer ni cerrarlas por inactividad, y reenviar la ruta
   `/.well-known/mercure`.
4. La sincronización opera en el **mismo origen** que la aplicación (`MERCURE_PUBLIC_URL` es relativa).
   Si cambias el dominio o el puerto, no fijes una URL absoluta distinta para el hub.
5. En las herramientas de desarrollo del navegador (pestaña *Red*) debe verse una conexión abierta a
   `/.well-known/mercure`; si aparece como rechazada, revisa que tu usuario tenga permiso para ver la
   estancia (la suscripción se autoriza con el mismo permiso que la pantalla).

## Copias de seguridad y actualización

Para copias de seguridad, restauración y actualización de versión, consulta
[Operación y mantenimiento](34-operacion-y-mantenimiento.md).
