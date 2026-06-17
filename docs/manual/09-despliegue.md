# Despliegue

Este capítulo detalla los dos modos de despliegue pensados para uso real: **binario nativo** (un centro sin 
infraestructura) y **Docker Compose** (servidor). Para el modo de desarrollo local, consulta
[Instalación y requisitos](01-instalacion-y-requisitos.md).

## Prueba rápida en tu ordenador (sin conocimientos técnicos)

¿Solo quieres ver cómo funciona Nexo FP en tu propio equipo? No necesitas instalar nada ni saber de
informática. En **tres pasos** tendrás la aplicación funcionando con datos de ejemplo (centros, empresas,
profesorado y estancias ya creados).

### Paso 1 · Descarga el archivo de tu equipo

Entra en la **[página de descargas (Releases)](https://github.com/reasol-edu/nexo-fp/releases)** y, en la
última versión, descarga el archivo que corresponda a tu ordenador:

![Página de Releases del proyecto en GitHub: la última versión con la lista de archivos descargables (Assets)](img/releases.png)

Los archivos descargables están en el apartado **Assets** de la última versión:

| Tu ordenador | Archivo a descargar |
|--------------|---------------------|
| **Windows** | `nexo-fp-…-windows-x86_64.zip` |
| **Mac con chip Apple** (M1, M2, M3, M4…) | `nexo-fp-…-macos-arm64.tar.gz` |
| **Mac con procesador Intel** | `nexo-fp-…-macos-x86_64.tar.gz` |
| **Linux** (PC habitual) | `nexo-fp-…-linux-x86_64.tar.gz` |
| **Linux ARM** (p. ej. Raspberry Pi) | `nexo-fp-…-linux-aarch64.tar.gz` |

> **¿No sabes qué Mac tienes?** Abre el menú Apple () arriba a la izquierda → **Acerca de este Mac**.
> Si aparece «Chip Apple M…» elige el archivo *arm64*; si aparece «Procesador Intel», el *x86_64*.

### Paso 2 · Descomprime el archivo

- **Windows / Mac:** haz doble clic en el archivo descargado; se creará una carpeta `nexo-fp-…`.
- **Linux:** clic derecho → *Extraer aquí* (o, en una terminal, `tar xzf nexo-fp-….tar.gz`).

### Paso 3 · Arranca con datos de demostración

Abre la carpeta que se ha creado y:

- **Windows:** haz doble clic en **`demo.bat`**.
- **Mac:** haz doble clic en **`demo.command`**.
- **Linux:** abre una terminal en la carpeta y ejecuta `./demo.sh`.

Espera unos segundos (la primera vez tarda un poco en prepararse) y abre tu navegador en
**[http://localhost:8080](http://localhost:8080)**. Entra con usuario **`admin`** y contraseña
**`admin`**.

![Pantalla de inicio de sesión de Nexo FP con el usuario «admin» introducido](img/login.png)

> **Aviso de seguridad en macOS.** Como la aplicación no está firmada con un certificado de Apple, la
> primera vez macOS la bloqueará con un mensaje del tipo *«No se puede abrir "demo.command" porque
> proviene de un desarrollador no identificado»* (en versiones recientes, *«Apple no ha podido verificar
> que "demo.command" esté libre de software malicioso»*). Es normal. Para autorizarla:
>
> 1. En el **Finder**, haz **clic secundario** sobre `demo.command` (clic con el botón derecho del
>    ratón, o mantén pulsada la tecla **Control** ⌃ mientras haces clic) y elige **Abrir** en el menú
>    contextual.
> 2. En el cuadro de diálogo que aparece, vuelve a pulsar **Abrir** para confirmar.
>
> Si usas **macOS Sequoia (15) o posterior** y el bloqueo no te ofrece la opción de abrir, ve al menú
> Apple () → **Ajustes del Sistema** → **Privacidad y seguridad**, baja hasta la sección **Seguridad**
> y pulsa **Abrir igualmente** junto al aviso sobre `demo.command`; confírmalo con **Touch ID** o tu
> contraseña de administrador.
>
> Solo hace falta hacerlo la primera vez: después, `demo.command` se abrirá con normalidad.

Para **detener** la aplicación, cierra la ventana negra (terminal) que se abrió, o pulsa `Ctrl + C` en
ella.

> ⚠️ El modo demostración **borra cualquier dato anterior** y carga datos de ejemplo cada vez que
> arrancas con `demo.*`. Para empezar con una instalación vacía y real, usa los scripts `start.*` en vez
> de `demo.*` (ver más abajo).

## Ejecución como binario nativo

El modo binario nativo está pensado para instalaciones sencillas sin Docker. Incluye un ejecutable de
[FrankenPHP](https://frankenphp.dev) que embebe el servidor web y PHP, y usa
[SQLite](https://www.sqlite.org) como base de datos, por lo que no necesita ningún software adicional
instalado en el sistema.

### Descarga

Descarga el paquete correspondiente a tu sistema operativo desde la página de releases del proyecto y
descomprímelo. El paquete contiene:

```
nexo-fp/
├── app/            ← código de la aplicación
├── data/           ← generado automáticamente (BD, caché, secreto)
├── frankenphp      ← ejecutable (frankenphp.exe en Windows)
├── Caddyfile       ← configuración del servidor web
├── start.sh        ← script de arranque (Linux / macOS)
├── start.bat       ← script de arranque (Windows CMD)
├── start.ps1       ← script de arranque (Windows PowerShell)
├── demo.sh         ← arranque cargando datos de demostración (Linux / macOS)
├── demo.command    ← igual que demo.sh, abrible con doble clic (solo macOS)
├── demo.bat        ← arranque cargando datos de demostración (Windows CMD)
└── demo.ps1        ← arranque cargando datos de demostración (Windows PowerShell)
```

### Primer arranque

**Linux / macOS:**

```bash
chmod +x frankenphp start.sh
./start.sh
```

**Windows (CMD):**

```bat
start.bat
```

**Windows (PowerShell):**

```powershell
.\start.ps1
```

Se puede especificar un puerto distinto al predeterminado (8080):

```bash
./start.sh 9000          # Linux / macOS
start.bat 9000           # Windows CMD
.\start.ps1 -Port 9000   # Windows PowerShell
```

La primera vez que se inicia, el script realiza automáticamente:

1. Genera un `APP_SECRET` aleatorio y lo guarda en `data/.secret`.
2. Crea la base de datos SQLite en `data/nexo-fp.db`.
3. Ejecuta las migraciones.
4. Crea el usuario administrador inicial (`admin` / `admin`) y el centro de prueba `IES Test`.
5. Precalienta la caché de Symfony.

La aplicación queda disponible en `http://localhost:8080` (o el puerto indicado).

> ⚠️ **Seguridad — cambia la contraseña por defecto.** El usuario inicial `admin` / `admin` se crea solo
> para el primer acceso. En cuanto entres, ve a **Mi perfil → Contraseña** y establece una contraseña
> robusta. En instalaciones reales, crea además tu propio administrador con
> [`app:create-admin`](08-comandos-de-consola.md#appcreate-admin). Nunca dejes `admin` / `admin` en una
> instalación accesible por red.

### Arranque con datos de demostración

Para probar la aplicación con datos de ejemplo (usuarios, centros, empresas y estancias precargadas),
usa los scripts `demo.*` en lugar de `start.*`. Son equivalentes al arranque normal pero cargan los
fixtures automáticamente (`LOAD_FIXTURES=true`):

```bash
./demo.sh                 # Linux / macOS
demo.bat                  # Windows CMD
.\demo.ps1                # Windows PowerShell
```

En macOS también puedes hacer **doble clic en `demo.command`** desde el Finder (la primera vez: clic
derecho → *Abrir*, para saltar el aviso de Gatekeeper).

Los scripts `demo.*` aceptan un puerto, igual que los de arranque (`./demo.sh 9000`). ⚠️ Cargar los datos
de demostración **borra los datos existentes**.

### macOS: aviso de Gatekeeper

La primera vez que se ejecuta en macOS, el sistema puede bloquear el binario por no estar firmado. El
script `start.sh` elimina la cuarentena automáticamente, pero si el problema persiste ejecuta:

```bash
xattr -d com.apple.quarantine frankenphp
```

### Variables de entorno {#variables-de-entorno-opcionales}

Esta es la **referencia única** de las variables de configuración. En el binario nativo se ajustan antes
de lanzar el script (tanto en Linux/macOS como en Windows); en Docker se definen en `.env.local`.

| Variable | Descripción | Valor por defecto |
|----------|-------------|-------------------|
| `PORT` | Puerto de escucha (binario nativo) | `8080` |
| `APP_SECRET` | Clave de seguridad (64 caracteres hexadecimales). En el binario se genera sola | *(generada)* |
| `DATABASE_URL` | Conexión a la base de datos (Docker usa PostgreSQL; el binario, SQLite) | *(según el modo)* |
| `DEFAULT_URI` | URL pública de la aplicación, usada en los enlaces de los emails | `http://localhost` |
| `APP_EXTERNAL_ENABLED` | Activar autenticación iSéneca (ver [Roles y permisos](03-roles-y-permisos.md#acceso-a-la-plataforma)) | `true` |
| `APP_EXTERNAL_URL` | URL del servicio iSéneca | *(URL oficial)* |
| `APP_EXTERNAL_URL_FORCE_SECURITY` | Verificar certificado TLS de iSéneca | `true` |
| `MAILER_DSN` | Transporte de correo para las [notificaciones](06-notificaciones-y-email.md) | `null://null` (desactivado) |
| `MAILER_FROM` | Dirección remitente de los emails automáticos | `no-responder@example.com` |
| `MESSENGER_TRANSPORT_DSN` | Cola de envío asíncrono de correos | `doctrine://default?auto_setup=0` |
| `MERCURE_JWT_SECRET` | Clave para firmar los avisos de [sincronización en vivo](#sincronizacion-en-vivo-mercure). En Docker es obligatoria; en el binario se genera sola | *(generada en el binario)* |
| `MERCURE_URL` | URL interna que usa la aplicación para publicar avisos (servidor→hub) | *(según el modo)* |
| `MERCURE_PUBLIC_URL` | URL pública (navegador→hub), relativa al mismo origen | `/.well-known/mercure` |
| `LOAD_FIXTURES` | Cargar datos de demostración al arrancar (⚠️ borra datos existentes) | `false` |

### Sincronización en vivo (Mercure) {#sincronizacion-en-vivo-mercure}

La pantalla de estancia se actualiza sola cuando varias personas gestionan los puestos formativos a la
vez (ver [Secciones de la aplicación](05-secciones-de-la-aplicacion.md#estancias)). Esta función usa
[Mercure](https://mercure.rocks), un protocolo de envío de eventos del servidor al navegador.

No requiere ningún servicio ni contenedor adicional: el **hub de Mercure va embebido en FrankenPHP**,
el mismo servidor de aplicaciones que se usa en los dos modos de despliegue. Solo hay que tener en
cuenta el secreto que firma los avisos:

- **Binario nativo:** el secreto se genera automáticamente en el primer arranque y se guarda en
  `data/.mercure_secret`. Los arranques posteriores reutilizan el mismo. No hay que hacer nada.
- **Docker:** define `MERCURE_JWT_SECRET` en `.env.local` (clave aleatoria, distinta de `APP_SECRET`).
  Genérala igual que `APP_SECRET`, con `php -r 'echo bin2hex(random_bytes(32));'`.

El canal solo transporta un aviso de cambio, nunca datos: cada navegador vuelve a pedir el contenido al
servidor, que lo renderiza aplicando los permisos de cada usuario. Si la aplicación se sirve sin hub
(por ejemplo, con el servidor de desarrollo `php -S`), la sincronización en vivo simplemente queda
inactiva y la pantalla se actualiza al recargar; el resto de la aplicación funciona igual.

En **desarrollo local** con `symfony server:start` no hay FrankenPHP, así que el hub no va embebido. El
overlay `compose.dev.yaml` levanta un hub aparte (imagen `dunglas/mercure`) junto a PostgreSQL; el
Symfony CLI lo detecta automáticamente e inyecta las variables `MERCURE_URL` y `MERCURE_PUBLIC_URL`
apuntando a ese contenedor, de modo que la sincronización en vivo funciona sin más configuración. Como
ese hub queda en otro puerto que el servidor local, se abre con suscripción anónima y CORS al origen de
desarrollo (`https://localhost:8000`); es una relajación **solo para desarrollo** y sin riesgo, porque el
canal sigue sin transportar datos. Si arrancas el servidor en otro puerto, ajusta `cors_origins` en
`compose.dev.yaml`.

## Despliegue con Docker

Modo recomendado para producción. La imagen incluye [FrankenPHP](https://frankenphp.dev) como servidor de
aplicaciones y usa [PostgreSQL](https://www.postgresql.org) 16 como base de datos.

### Preparación

Copia el fichero de ejemplo y edita los valores. Usa `.env.local` (Git lo ignora, así que tus
secretos no se versionan y el `.env` del repositorio queda intacto):

```bash
cp .env.example .env.local
```

Indica a Docker Compose que use ese fichero exportándolo una vez en tu sesión. Así todos los comandos
`docker compose` de este capítulo lo leerán sin necesidad de repetir ningún flag:

```bash
export COMPOSE_ENV_FILES=.env.local
```

> Como alternativa, añade `--env-file .env.local` a cada comando `docker compose`. Si quieres que la
> aplicación se inicie sola al reiniciar el servidor, consulta
> [Arranque automático al reiniciar el servidor](#arranque-automatico-al-reiniciar-el-servidor).

Los campos obligatorios son:

- **`APP_SECRET`** — clave aleatoria de 64 caracteres hexadecimales. Genera una accediendo
  a [esta página web](https://numbergenerator.org/hex-code-generator#!numbers=1&length=64&addfilters=) o, si
  tienes PHP instalado, con:
  ```bash
  php -r 'echo bin2hex(random_bytes(32));'
  ```
- **`DB_PASSWORD`** — contraseña de la base de datos PostgreSQL.
- **`MERCURE_JWT_SECRET`** — clave para firmar los avisos de
  [sincronización en vivo](#sincronizacion-en-vivo-mercure), distinta de `APP_SECRET`. Genérala con el
  mismo comando.

### Arranque

```bash
docker compose up -d
```

La primera vez que se inicia, el contenedor realiza automáticamente lo siguiente:

1. Ejecuta las migraciones de base de datos.
2. Crea el usuario administrador inicial (`admin` / `admin`) y el centro de prueba `IES Test`.
3. Inicializa la caché de Symfony.

La aplicación queda disponible en `http://localhost` (puerto 80 por defecto).

El stack levanta tres contenedores: `app` (servidor FrankenPHP), `database` (PostgreSQL) y `worker`, que
procesa el envío asíncrono de los correos y dispara el recordatorio diario de firma programado con
Symfony Scheduler (consulta [Notificaciones por email](06-notificaciones-y-email.md#envio-asincrono)).

> ⚠️ **Seguridad — cambia la contraseña por defecto.** El usuario inicial `admin` / `admin` se crea solo
> para el primer acceso. En cuanto entres, ve a **Mi perfil → Contraseña** y establece una contraseña
> robusta. En producción, crea además tu propio administrador con
> [`app:create-admin`](08-comandos-de-consola.md#appcreate-admin). Nunca dejes `admin` / `admin` en una
> instalación accesible por red.

### Datos de demostración

Para arrancar con datos de prueba (usuarios, centros, empresas y estancias precargadas), cambia en tu
`.env.local` el valor de la variable que ya existe (no añadas una línea nueva: una clave duplicada haría
que Docker Compose use la última aparición y podría seguir valiendo `false`):

```dotenv
LOAD_FIXTURES=true
```

El contenedor cargará los fixtures automáticamente en cada arranque. ⚠️ Esta opción **borra todos los
datos existentes**.

### HTTPS con Let's Encrypt

Para habilitar HTTPS automático, edita `.env.local` con tu dominio real:

```dotenv
SERVER_NAME=nexo.tudominio.es
DEFAULT_URI=https://nexo.tudominio.es
HTTP_PORT=80
HTTPS_PORT=443
```

FrankenPHP (Caddy) gestionará el certificado TLS sin configuración adicional.

### Datos persistentes

Los datos se almacenan en el directorio `./data/` del proyecto:

- `./data/postgres/` — base de datos PostgreSQL.
- `./data/var/` — caché, logs y sesiones de Symfony.

### Arranque automático al reiniciar el servidor

Los tres servicios del `compose.yaml` llevan `restart: unless-stopped`, así que **el demonio de Docker
vuelve a levantarlos solo tras un reinicio del servidor**, sin ninguna configuración adicional de Nexo FP.
Para el caso habitual basta con dos cosas:

1. Que Docker se inicie con el sistema:
   ```bash
   sudo systemctl enable --now docker
   ```
2. Haber arrancado el stack al menos una vez con `docker compose up -d` y no haberlo detenido
   explícitamente con `docker compose stop` o `docker compose down`.

> ⚠️ **`.env.local` solo se lee al hacer `up`.** Las variables de `.env.local` se graban en los
> contenedores **cuando se crean** (`docker compose up -d`). Los reinicios automáticos reutilizan esos
> contenedores tal cual y **no vuelven a leer el fichero**. Por eso, si más adelante editas `.env.local`
> (o el propio `compose.yaml`), debes volver a ejecutar `docker compose up -d` para que los cambios surtan
> efecto; un simple reinicio del servidor no los recogerá.

Si además quieres que el stack se **recree** desde cero en cada arranque —por ejemplo, para que siempre
aplique los últimos valores de `.env.local` aunque previamente se hubiera hecho `down`— define una unidad
de **systemd**:

```ini
# /etc/systemd/system/nexo-fp.service
[Unit]
Description=Nexo FP (Docker Compose)
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/opt/nexo-fp          # ruta donde están compose.yaml y .env.local
Environment=COMPOSE_ENV_FILES=.env.local
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nexo-fp.service
```

`Environment=COMPOSE_ENV_FILES=.env.local` (ruta relativa a `WorkingDirectory`) es lo que hace que el
arranque automático use tu fichero de variables; equivale al `export` que harías en tu sesión. Como
alternativa, usa `ExecStart=/usr/bin/docker compose --env-file .env.local up -d`. Comprueba la ruta del
ejecutable de Docker con `which docker` (en algunas distribuciones es `/usr/local/bin/docker`).

| Situación | Solo `restart: unless-stopped` | Unidad de systemd |
|---|:---:|:---:|
| Reinicio del servidor | ✅ | ✅ |
| Tras `docker compose down` | ❌ no vuelve | ✅ se recrea |
| Recoge cambios de `.env.local` / `compose.yaml` sin intervención | ❌ requiere `up` manual | ✅ en cada arranque |

### Actualización

```bash
docker compose pull   # o: docker compose build
docker compose up -d
```

Las migraciones se aplican automáticamente en cada arranque.

### Comandos útiles

```bash
# Ver logs en tiempo real
docker compose logs -f app

# Abrir una shell en el contenedor
docker compose exec app sh

# Crear un centro educativo adicional
docker compose exec app php bin/console app:create-educational-centre

# Crear un administrador adicional
docker compose exec app php bin/console app:create-admin
```
