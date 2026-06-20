# Despliegue

Este capítulo contiene las instrucciones completas de todos los modos de despliegue: **prueba rápida**
(sin conocimientos técnicos), **binario nativo**, **Docker Compose**, **Plesk**,
**Ubuntu Server 26.04** y **desarrollo local**.
Para elegir el modo más adecuado, consulta la tabla comparativa en
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

!!! question "¿Qué Mac tengo?"
    Abre el menú Apple () arriba a la izquierda → **Acerca de este Mac**.
    Si aparece «Chip Apple M…» elige el archivo *arm64*; si aparece «Procesador Intel», el *x86_64*.

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

??? warning "Aviso de seguridad en macOS"
    Como la aplicación no está firmada con un certificado de Apple, la primera vez macOS la bloqueará
    con un mensaje del tipo *«No se puede abrir "demo.command" porque proviene de un desarrollador no
    identificado»* (en versiones recientes, *«Apple no ha podido verificar que "demo.command" esté libre
    de software malicioso»*). Es normal. Para autorizarla:

    1. En el **Finder**, haz **clic secundario** sobre `demo.command` (clic con el botón derecho del
       ratón, o mantén pulsada la tecla **Control** ⌃ mientras haces clic) y elige **Abrir** en el menú
       contextual.
    2. En el cuadro de diálogo que aparece, vuelve a pulsar **Abrir** para confirmar.

    Si usas **macOS Sequoia (15) o posterior** y el bloqueo no te ofrece la opción de abrir, ve al menú
    Apple () → **Ajustes del Sistema** → **Privacidad y seguridad**, baja hasta la sección **Seguridad**
    y pulsa **Abrir igualmente** junto al aviso sobre `demo.command`; confírmalo con **Touch ID** o tu
    contraseña de administrador.

    Solo hace falta hacerlo la primera vez: después, `demo.command` se abrirá con normalidad.

Para **detener** la aplicación, cierra la ventana negra (terminal) que se abrió, o pulsa `Ctrl + C` en
ella.

!!! danger "El modo demostración borra los datos existentes"
    Cada vez que arrancas con `demo.*` se borran todos los datos anteriores y se cargan los de ejemplo.
    Para empezar con una instalación vacía y real, usa los scripts `start.*` en vez de `demo.*`.

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

!!! danger "Cambia la contraseña por defecto"
    El usuario inicial `admin` / `admin` se crea solo para el primer acceso. En cuanto entres, ve a
    **Mi perfil → Contraseña** y establece una contraseña robusta. En instalaciones reales, crea además
    tu propio administrador con [`app:create-admin`](08-comandos-de-consola.md#appcreate-admin).
    Nunca dejes `admin` / `admin` en una instalación accesible por red.

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

### Actualizar a una nueva versión

1. Detén la aplicación (cierra la terminal o pulsa Ctrl + C si se ejecuta en primer plano).
2. Descarga el paquete de la nueva versión desde la
   [página de Releases](https://github.com/reasol-edu/nexo-fp/releases) y descomprímelo.
3. Copia los archivos nuevos sobre la carpeta existente, sobreescribiendo los que ya haya.
   El directorio `data/` (base de datos, secretos y caché) **no forma parte del paquete**, por lo que
   se conserva automáticamente al extraer encima.
4. Vuelve a ejecutar el script de arranque. En cada arranque se aplican automáticamente las
   migraciones pendientes y se regenera la caché.

!!! tip "Alternativa limpia"
    Si prefieres descomprimir en una carpeta nueva, copia el directorio `data/` desde la instalación
    anterior antes de arrancar. También puedes conservar un fichero `.env.local` si lo habías creado
    para personalizar alguna variable.

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

!!! tip "Alternativa"
    Añade `--env-file .env.local` a cada comando `docker compose`. Si quieres que la aplicación se
    inicie sola al reiniciar el servidor, consulta
    [Arranque automático al reiniciar el servidor](#arranque-automatico-al-reiniciar-el-servidor).

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

!!! danger "Cambia la contraseña por defecto"
    El usuario inicial `admin` / `admin` se crea solo para el primer acceso. En cuanto entres, ve a
    **Mi perfil → Contraseña** y establece una contraseña robusta. En producción, crea además tu propio
    administrador con [`app:create-admin`](08-comandos-de-consola.md#appcreate-admin).
    Nunca dejes `admin` / `admin` en una instalación accesible por red.

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

!!! warning "`.env.local` solo se lee al hacer `up`"
    Las variables de `.env.local` se graban en los contenedores **cuando se crean**
    (`docker compose up -d`). Los reinicios automáticos reutilizan esos contenedores tal cual y
    **no vuelven a leer el fichero**. Por eso, si más adelante editas `.env.local` (o el propio
    `compose.yaml`), debes volver a ejecutar `docker compose up -d` para que los cambios surtan efecto;
    un simple reinicio del servidor no los recogerá.

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

## Despliegue en Plesk

Opción para centros que ya disponen de un **VPS o servidor dedicado gestionado con Plesk**. El stack
es PHP-FPM + MySQL + Apache/Nginx estándar: no requiere Docker ni FrankenPHP. A cambio, la
[sincronización en vivo](#sincronizacion-en-vivo-mercure) no está disponible en este modo (necesita
FrankenPHP). Se requiere **acceso SSH** a la suscripción.

### Requisitos previos

- **PHP 8.4** en modo **PHP-FPM** con las siguientes extensiones activas:
  `ctype`, `curl`, `dom`, `gd`, `iconv`, `intl`, `libxml`, `mbstring`, `pdo_mysql`, `xml`, `opcache`
- **MySQL 8.0+** o **MariaDB 10.6+** (o PostgreSQL 12+ si el servidor lo ofrece; ver nota al final)
- **Composer** (disponible en la mayoría de servidores Plesk vía SSH; compruébalo con `composer --version`)
- Acceso SSH a la suscripción

### 1. Obtener los archivos

!!! tip "Usa Git para facilitar las actualizaciones"
    Clona el repositorio en un directorio **fuera de `httpdocs/`** (por ejemplo `~/apps/nexo-fp/`):

    ```bash
    git clone https://github.com/reasol-edu/nexo-fp.git ~/apps/nexo-fp
    ```

    Así, actualizar a una nueva versión se reduce a un `git pull` y tres comandos.

Si prefieres no usar Git, descarga el código fuente desde la
[página de Releases](https://github.com/reasol-edu/nexo-fp/releases) en **Assets → Source code (zip)**
y descomprímelo en el servidor.

### 2. Configurar el dominio en Plesk

En el panel de Plesk, ve a **Dominios → [tu dominio] → Configuración de alojamiento web** y cambia el
campo **«Directorio raíz del documento»** para que apunte a la carpeta `public/` del proyecto:

```
/var/www/vhosts/tudominio.es/apps/nexo-fp/public
```

(Ajusta la ruta según donde hayas colocado los archivos.)

### 3. Configurar PHP 8.4 en Plesk

En la sección **PHP** del dominio:

1. Selecciona la versión **8.4** y el modo **FPM**.
2. En **«Configuración adicional del manejador PHP»** añade o ajusta:

   ```ini
   memory_limit = 256M
   max_execution_time = 60
   ```

3. Activa las extensiones necesarias si aparecen desactivadas en la lista.

### 4. Variables de entorno

Crea el fichero `.env.local` en la raíz del proyecto con los valores mínimos obligatorios:

```bash
APP_ENV=prod
APP_SECRET=pon_aqui_64_caracteres_hexadecimales_aleatorios
DATABASE_URL="mysql://usuario:contraseña@localhost:3306/nombre_bd?serverVersion=8.0&charset=utf8mb4"
MIGRATIONS_PATH=migrations/mysql
DEFAULT_URI=https://tudominio.es
```

!!! tip "Generar APP_SECRET"
    Ejecuta este comando en el servidor y copia el resultado en `APP_SECRET`:

    ```bash
    php -r "echo bin2hex(random_bytes(32));"
    ```

El resto de variables (correo, autenticación externa, paginación…) son opcionales y equivalentes a
las del despliegue con Docker; consulta la sección [Variables de entorno](#variables-de-entorno-opcionales)
de ese apartado.

!!! note "PostgreSQL como alternativa"
    Si el servidor dispone de PostgreSQL en lugar de MySQL, cambia `DATABASE_URL` al formato:

    ```
    postgresql://usuario:contraseña@localhost:5432/nombre_bd?serverVersion=16&charset=utf8
    ```

    y ajusta también `MIGRATIONS_PATH=migrations/postgresql`.

### 5. Instalar dependencias y compilar los assets

```bash
cd ~/apps/nexo-fp
composer install --no-dev --optimize-autoloader
php bin/console tailwind:build --env=prod
php bin/console asset-map:compile
```

!!! info "Por qué hace falta compilar los assets"
    Los archivos CSS y JS generados no se incluyen en el repositorio. `tailwind:build` genera
    el CSS a partir de las plantillas (sin necesidad de Node.js) y `asset-map:compile` copia
    y versiona todos los assets en `public/assets/`.

### 6. Crear la base de datos y configurar la aplicación

1. Crea la base de datos y el usuario desde el panel de Plesk (**Bases de datos**).
2. Ajusta el usuario y la contraseña en `DATABASE_URL` del `.env.local`.
3. Por SSH, ejecuta:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:setup --no-interaction
```

!!! danger "Cambia la contraseña por defecto"
    `app:setup` crea el usuario `admin` con contraseña `admin`. **Cámbiala inmediatamente** en
    **Administración → Perfil** antes de que nadie más acceda.

### 7. Correos y recordatorios (worker vía cron)

Los correos de aviso y los recordatorios diarios de firma los procesa un *worker* de Symfony
Messenger. Sin él la aplicación funciona, pero **no se envían emails ni se ejecutan los recordatorios
programados**.

!!! info "Configurar el worker en Plesk"
    Ve a **Tareas programadas** en el panel de Plesk y crea una tarea con periodicidad **cada hora**:

    ```
    /usr/bin/php /ruta/absoluta/a/nexo-fp/bin/console messenger:consume async scheduler_default --time-limit=3540 --memory-limit=128M
    ```

    El parámetro `--time-limit=3540` (59 minutos) hace que el proceso termine antes de que arranque
    la siguiente ejecución, evitando solapamientos.

El transporte `async` lleva los correos pendientes y `scheduler_default` gestiona el recordatorio
diario de firma. Si prefieres disparar el recordatorio directamente desde cron en lugar del
Scheduler, consulta [Recordatorios de firma](10-operacion-y-mantenimiento.md#recordatorios-de-firma).

### 8. HTTPS y dominio definitivo

Activa **Let's Encrypt** desde la sección **SSL/TLS** del dominio en Plesk. Una vez habilitado el
certificado, actualiza `DEFAULT_URI` en `.env.local`:

```bash
DEFAULT_URI=https://tudominio.es
```

Y limpia la caché para que Symfony la regenere con la URL nueva:

```bash
php bin/console cache:clear
```

### 9. Sincronización en vivo

!!! warning "No disponible en este modo de despliegue"
    La actualización automática de la pantalla de estancia requiere el hub Mercure embebido en
    FrankenPHP. En un despliegue con Plesk (PHP-FPM estándar) esta función **no está disponible**:
    la aplicación funciona con normalidad, pero las pantallas de estancia hay que recargarlas
    manualmente para ver los cambios de otros usuarios.

    Para obtener sincronización en vivo, usa el [despliegue con Docker Compose](#despliegue-con-docker).
    Consulta también la sección [Sincronización en vivo](#sincronizacion-en-vivo-mercure) para más
    detalles técnicos.

### Actualizar a una nueva versión

1. Descarga o actualiza los archivos (con Git: `git pull`).
2. Instala las dependencias actualizadas:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Recompila los assets:
   ```bash
   php bin/console tailwind:build --env=prod
   php bin/console asset-map:compile
   ```
4. Aplica las migraciones de base de datos:
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
5. Regenera la caché:
   ```bash
   rm -rf var/cache/prod
   php bin/console cache:warmup --env=prod --no-debug
   ```

!!! tip "Las migraciones son seguras de re-ejecutar"
    Si una versión no incluye cambios de esquema, `doctrine:migrations:migrate` termina en segundos
    sin modificar nada. Puedes ejecutarlo en cada actualización sin riesgo.

### Copias de seguridad

Guarda periódicamente:

- El directorio **`var/`** del proyecto (caché, logs, sesiones).
- Un volcado de la base de datos:

```bash
mysqldump -u usuario -p nombre_bd > backup-$(date +%Y%m%d).sql
```

Consulta [Operación y mantenimiento](10-operacion-y-mantenimiento.md#copias-de-seguridad) para más
contexto sobre protección de datos y política de conservación.

## Despliegue en Ubuntu Server 26.04

Opción para centros que disponen de un **VPS o servidor dedicado con Ubuntu Server 26.04 LTS**.
Usa el binario de FrankenPHP con **PostgreSQL nativo** y dos servicios **systemd**. No requiere
Docker. A diferencia del despliegue en Plesk, incluye la
[sincronización en vivo](#sincronizacion-en-vivo-mercure) (el hub Mercure va embebido en FrankenPHP).
Se requiere acceso SSH con sudo.

### Instalación automatizada

El script `install-ubuntu.sh` (incluido en el paquete descargado o disponible directamente en el
repositorio) realiza la instalación completa sin intervención manual.

**Requisitos antes de ejecutarlo:**

- Ubuntu Server 26.04 LTS con acceso a Internet y usuario con sudo.
- Un **nombre de dominio** que apunte al servidor (necesario para el certificado TLS automático).
- Puertos **80**, **443/tcp** y **443/udp** accesibles desde Internet.

**Ejecución:**

Desde el directorio donde descomprimiste el paquete:

```bash
sudo bash install-ubuntu.sh
```

O directamente desde el repositorio, sin descargar el paquete:

```bash
curl -fsSL https://raw.githubusercontent.com/reasol-edu/nexo-fp/main/dist/install-ubuntu.sh \
  | sudo bash
```

El script solicita tres datos: el **nombre de dominio**, la **contraseña de la base de datos** y la
**dirección de correo remitente** (opcional). A continuación realiza automáticamente los pasos
descritos en la sección siguiente.

!!! danger "Cambia la contraseña por defecto"
    Al terminar, la aplicación queda accesible en `https://tudominio.es` con `admin` / `admin`.
    **Cámbiala inmediatamente** en **Administración → Perfil** antes de dar acceso a nadie más.

### Instalación manual paso a paso

Si prefieres controlar cada paso o adaptar la instalación a tu entorno, sigue esta secuencia:

#### 1. Instalar PostgreSQL

```bash
sudo apt-get update && sudo apt-get install -y postgresql postgresql-client curl
sudo -u postgres psql -c "CREATE USER nexo WITH PASSWORD 'contraseña_segura';"
sudo -u postgres psql -c "CREATE DATABASE nexo OWNER nexo;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE nexo TO nexo;"
```

#### 2. Configurar el cortafuegos

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp   # HTTP/3 (QUIC)
sudo ufw enable
```

#### 3. Crear el usuario del sistema y el directorio de instalación

```bash
sudo useradd -r -d /opt/nexo-fp -s /usr/sbin/nologin nexofp
sudo mkdir -p /opt/nexo-fp
sudo chown nexofp:nexofp /opt/nexo-fp
```

#### 4. Descargar el binario

Desde la [página de Releases](https://github.com/reasol-edu/nexo-fp/releases), copia el enlace
del archivo `nexo-fp-VERSION-linux-x86_64.tar.gz` (o `linux-aarch64` para ARM) y extráelo:

```bash
VERSION=X.Y.Z   # reemplaza por la versión actual
sudo -u nexofp bash -c "
  curl -fsSL https://github.com/reasol-edu/nexo-fp/releases/download/v${VERSION}/nexo-fp-${VERSION}-linux-x86_64.tar.gz \
  | tar xzf - -C /opt/nexo-fp --strip-components=1
"
```

#### 5. Crear el fichero de configuración

```bash
sudo -u nexofp nano /opt/nexo-fp/.env.local
```

Contenido mínimo obligatorio:

```bash
SERVER_ADDR=nexo.tudominio.es
DEFAULT_URI=https://nexo.tudominio.es
DATABASE_URL=postgresql://nexo:contraseña_segura@localhost:5432/nexo?serverVersion=16&charset=utf8
MIGRATIONS_PATH=migrations/postgresql
MAILER_DSN=null://null
MAILER_FROM=nexo-fp@tudominio.es
```

`SERVER_ADDR` con el nombre de dominio (sin puerto) activa el **HTTPS automático** de
FrankenPHP/Caddy vía Let's Encrypt.

#### 6. Crear los scripts de arranque

Copia los ficheros `nexo-start.sh` y `nexo-worker.sh` desde el paquete, o ejecútalos desde la
sección anterior con `install-ubuntu.sh`.

Los scripts leen `.env.local`, generan `APP_SECRET` y `MERCURE_JWT_SECRET` automáticamente en el
primer arranque (guardados en `data/`), escriben el fichero `app/.env` que necesita Symfony y
lanzan en primer plano el servidor o el worker respectivamente.

#### 7. Instalar los servicios systemd

```bash
sudo tee /etc/systemd/system/nexo-fp.service > /dev/null << 'UNIT'
[Unit]
Description=Nexo FP (FrankenPHP)
After=network-online.target postgresql.service
Wants=network-online.target
Requires=postgresql.service

[Service]
Type=simple
User=nexofp
Group=nexofp
WorkingDirectory=/opt/nexo-fp
ExecStart=/opt/nexo-fp/nexo-start.sh
Restart=on-failure
RestartSec=5
TimeoutStopSec=30
LimitNOFILE=65536
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE

[Install]
WantedBy=multi-user.target
UNIT

sudo tee /etc/systemd/system/nexo-fp-worker.service > /dev/null << 'UNIT'
[Unit]
Description=Nexo FP Worker (Messenger + Scheduler)
After=nexo-fp.service
Requires=nexo-fp.service

[Service]
Type=simple
User=nexofp
Group=nexofp
WorkingDirectory=/opt/nexo-fp
ExecStart=/opt/nexo-fp/nexo-worker.sh
Restart=always
RestartSec=10
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
UNIT

sudo systemctl daemon-reload
sudo systemctl enable --now nexo-fp nexo-fp-worker
```

!!! info "AmbientCapabilities"
    La directiva `AmbientCapabilities=CAP_NET_BIND_SERVICE` permite que el proceso (ejecutado como
    `nexofp`, sin privilegios de root) escuche en los puertos 80 y 443.

#### Comandos útiles

```bash
# Estado de los servicios
sudo systemctl status nexo-fp nexo-fp-worker

# Seguir los logs en tiempo real
sudo journalctl -u nexo-fp -f
sudo journalctl -u nexo-fp-worker -f

# Reiniciar tras cambiar .env.local
sudo systemctl restart nexo-fp nexo-fp-worker
```

### Actualización a una nueva versión

1. Descarga el nuevo paquete y detén los servicios:

   ```bash
   VERSION=X.Y.Z
   curl -fsSL https://github.com/reasol-edu/nexo-fp/releases/download/v${VERSION}/nexo-fp-${VERSION}-linux-x86_64.tar.gz \
     -o /tmp/nexo-fp-new.tar.gz
   sudo systemctl stop nexo-fp-worker nexo-fp
   ```

2. Extrae el nuevo paquete sobre la instalación existente. El directorio `data/` (secretos y base de
   datos) y el fichero `.env.local` no están en el paquete, por lo que se conservan intactos:

   ```bash
   sudo -u nexofp tar xzf /tmp/nexo-fp-new.tar.gz -C /opt/nexo-fp --strip-components=1
   ```

3. Vuelve a arrancar los servicios. `nexo-start.sh` aplica automáticamente las migraciones
   pendientes y regenera la caché:

   ```bash
   sudo systemctl start nexo-fp nexo-fp-worker
   ```

### Copias de seguridad

- Los secretos (`data/.secret`, `data/.mercure_secret`) y la configuración (`data/.env.local`) están
  en `/opt/nexo-fp/`. Inclúyelos en tus copias.
- Haz un volcado periódico de la base de datos:

  ```bash
  sudo -u postgres pg_dump nexo > backup-$(date +%Y%m%d).sql
  ```

Consulta [Operación y mantenimiento](10-operacion-y-mantenimiento.md#copias-de-seguridad) para más
contexto.

## Desarrollo local

Para contribuir al proyecto o ejecutarlo desde el código fuente. Requisitos: PHP 8.4+, Composer y Docker
Compose (solo para la base de datos).

```bash
# 1. Clona el repositorio y copia el entorno
cp .env.example .env.local            # ajusta si es necesario
export COMPOSE_ENV_FILES=.env.local   # Compose usará .env.local

# 2. Levanta solo PostgreSQL con el overlay de desarrollo
docker compose -f compose.yaml -f compose.dev.yaml up -d

# 3. Instala dependencias e inicializa la base de datos
composer install
make migrate
php bin/console app:setup

# 4. Arranca el servidor de desarrollo
symfony server:start          # o: php -S localhost:8000 -t public/
```

Accede a **https://localhost:8000** (o **http://localhost:8000** con `php -S`) con `admin` / `admin`.

!!! note "Overlay de desarrollo"
    `compose.dev.yaml` se combina con `-f` y expone PostgreSQL en el puerto 5432, dejando el servicio
    PHP (`app`) tras el perfil `production`; por eso el comando anterior solo arranca la base de datos.
    En producción se usa únicamente `compose.yaml` (`docker compose up -d`), que levanta también la
    aplicación.

### Cargar datos de demostración

```bash
make fixtures
```

Consulta `DEMO.md` en la raíz del repositorio para ver los usuarios, centros y escenarios disponibles.

### Ejecutar los tests

```bash
make test
```

### Análisis estático

```bash
php vendor/bin/phpstan analyse
```

### Actualizar desde el repositorio

```bash
# 1. Descarga los últimos cambios
git pull

# 2. Actualiza las dependencias de PHP
composer install

# 3. Aplica las migraciones pendientes
make migrate
```

!!! tip "Revisa `.env.example` tras cada actualización"
    Si una nueva versión añade variables de entorno, aparecen en `.env.example`. Compara con
    tu `.env.local` y añade las que necesites.
