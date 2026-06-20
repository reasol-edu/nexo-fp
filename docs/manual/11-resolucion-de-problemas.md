# Resolución de problemas

Soluciones a las dudas y los contratiempos más habituales. Si no encuentras tu caso, revisa también el
capítulo [Operación y mantenimiento](10-operacion-y-mantenimiento.md).

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
[Prueba rápida → aviso de seguridad en macOS](09-despliegue.md#prueba-rapida-en-tu-ordenador-sin-conocimientos-tecnicos).

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
[`app:create-admin`](08-comandos-de-consola.md#appcreate-admin).

### El navegador dice «la conexión no es privada» en desarrollo

El servidor de desarrollo (`symfony server:start`) usa un certificado **autofirmado** en
`https://localhost:8000`. Es lo esperado: acepta la excepción de seguridad del navegador, o usa
`http://localhost:8000` arrancando con `php -S`.

### Un docente no ve ninguna estancia, empresa o sección

El acceso depende de los roles, que **se derivan de los datos** del centro (equipo directivo, jefatura de
familia, coordinación de FP dual, tutoría de grupo, docente de enlace…). Un docente sin ninguna de esas
asignaciones solo ve el panel de inicio y su perfil. Revisa sus asignaciones y consulta
[Roles y permisos](03-roles-y-permisos.md).

## No funciona el correo

### No llegan los emails

1. Comprueba que el correo está **activado**: `MAILER_DSN` debe apuntar a un servidor SMTP real (por
   defecto es `null://null`, que descarta los mensajes). Ver
   [Notificaciones por email](06-notificaciones-y-email.md).
2. El *worker* de la cola debe estar en marcha (con Docker Compose y con el binario nativo se lanza
   solo). Revisa los [mensajes fallidos](10-operacion-y-mantenimiento.md#correos-en-cola-messenger).
3. Comprueba que la notificación está habilitada en [Ajustes](07-ajustes.md) (global, centro y personal).
4. Los destinatarios **sin dirección de email** registrada se omiten de forma silenciosa.

## La pantalla de estancia no se actualiza sola

La [sincronización en vivo](09-despliegue.md#sincronizacion-en-vivo-mercure) puede quedar inactiva por
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

## Problemas con la importación de estudiantes

### El CSV no crea estudiantes o faltan grupos

- El fichero debe tener la **primera fila de encabezados** con los nombres de columna exactos y solo se
  importan las filas con `Estado Matrícula` **vacío**. Revisa el
  [formato del CSV](02-primeros-pasos.md#formato-del-csv-de-importacion).
- Si aparecen «unidades no reconocidas», los grupos del curso no se llaman igual que la columna `Unidad`
  de Séneca. Renombra los grupos para que coincidan exactamente (ver
  [Primeros pasos, paso 3](02-primeros-pasos.md#3-estructurar-la-oferta-formativa-del-curso-academico-equipo-directivo)).

## Copias de seguridad y actualización

Para copias de seguridad, restauración y actualización de versión, consulta
[Operación y mantenimiento](10-operacion-y-mantenimiento.md).
