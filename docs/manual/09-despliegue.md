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

> **Aviso de seguridad en Mac.** La primera vez, macOS puede decir que la aplicación es de un
> «desarrollador no identificado». Es normal (el programa no está firmado). En lugar de hacer doble clic,
> haz **clic derecho sobre `demo.command` → Abrir** y confirma en el aviso: solo hace falta la primera vez.

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

### Variables de entorno opcionales

Tanto en Linux/macOS como en Windows se pueden ajustar antes de lanzar el script:

| Variable | Descripción | Valor por defecto |
|----------|-------------|-------------------|
| `PORT` | Puerto de escucha | `8080` |
| `APP_EXTERNAL_ENABLED` | Activar autenticación iSéneca | `true` |
| `APP_EXTERNAL_URL` | URL del servicio iSéneca | *(URL oficial)* |
| `APP_EXTERNAL_URL_FORCE_SECURITY` | Verificar certificado TLS de iSéneca | `true` |
| `MAILER_DSN` | Transporte de correo para las [notificaciones](06-notificaciones-y-email.md) | `null://null` (desactivado) |
| `MAILER_FROM` | Dirección remitente de los emails automáticos | `no-responder@example.com` |
| `LOAD_FIXTURES` | Cargar datos de demostración al arrancar (⚠️ borra datos existentes) | `false` |

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

> Como alternativa, añade `--env-file .env.local` a cada comando `docker compose`. Para que el arranque
> automático del servidor (p. ej. con systemd) también lo use, define `COMPOSE_ENV_FILES=.env.local` en
> el entorno del servicio.

Los campos obligatorios son:

- **`APP_SECRET`** — clave aleatoria de 64 caracteres hexadecimales. Genera una accediendo
  a [esta página web](https://numbergenerator.org/hex-code-generator#!numbers=1&length=64&addfilters=) o, si
  tienes PHP instalado, con:
  ```bash
  php -r 'echo bin2hex(random_bytes(32));'
  ```
- **`DB_PASSWORD`** — contraseña de la base de datos PostgreSQL.

### Arranque

```bash
docker compose up -d
```

La primera vez que se inicia, el contenedor realiza automáticamente lo siguiente:

1. Ejecuta las migraciones de base de datos.
2. Crea el usuario administrador inicial (`admin` / `admin`) y el centro de prueba `IES Test`.
3. Inicializa la caché de Symfony.

La aplicación queda disponible en `http://localhost` (puerto 80 por defecto).

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
