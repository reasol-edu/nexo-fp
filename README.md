NEXO-FP
========

Aplicación web para gestionar la asignación de puestos formativos en la fase de formación en empresas u organismo equiparado.

Este proyecto está desarrollado en PHP utilizando [Symfony] 8.1 y otros muchos componentes que se instalan usando
[Composer].

## Requisitos

Según el modo de despliegue elegido, los requisitos son distintos:

| Modo | Requisitos |
|------|-----------|
| Docker | Docker Engine 24+ y Docker Compose v2 |
| Binario nativo | Sin requisitos adicionales (todo incluido) |
| Desarrollo local | PHP 8.4+, Composer, PostgreSQL 16+ o SQLite |

## Despliegue con Docker

Este es el modo recomendado para entornos de producción. La imagen incluye [FrankenPHP] como servidor de aplicaciones
y usa [PostgreSQL] 16 como base de datos.

### Preparación

Copia el fichero de ejemplo y edita los valores:

```bash
cp .env.example .env
```

Los campos obligatorios son:

- **`APP_SECRET`** — clave aleatoria de 64 caracteres hexadecimales. Genera una con:
  ```bash
  php -r 'echo bin2hex(random_bytes(32));'
  ```
- **`DB_PASSWORD`** — contraseña de la base de datos PostgreSQL.

### Arranque

```bash
docker compose up -d
```

La primera vez que se inicia, el contenedor realiza automáticamente:

1. Ejecuta las migraciones de base de datos.
2. Crea el usuario administrador inicial (`admin` / `admin`) y el centro de prueba `IES Test`.
3. Precalienta la caché de Symfony.

La aplicación queda disponible en `http://localhost` (puerto 80 por defecto).

### HTTPS con Let's Encrypt

Para habilitar HTTPS automático, edita `.env` con tu dominio real:

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

---

## Ejecución como binario nativo

El modo binario nativo está pensado para instalaciones sencillas sin Docker. Incluye un ejecutable de
[FrankenPHP] que embebe el servidor web y PHP, y usa [SQLite] como base de datos, por lo que no necesita
ningún software adicional instalado en el sistema.

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
└── start.ps1       ← script de arranque (Windows PowerShell)
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

### Datos persistentes

Todo lo generado en tiempo de ejecución se guarda en el directorio `data/` dentro del paquete. Para hacer
una copia de seguridad basta con copiar ese directorio.

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
| `APP_PAGE_SIZE` | Elementos por página | `20` |
| `APP_EXTERNAL_ENABLED` | Activar autenticación iSéneca | `true` |
| `APP_EXTERNAL_URL` | URL del servicio iSéneca | *(URL oficial)* |
| `APP_EXTERNAL_URL_FORCE_SECURITY` | Verificar certificado TLS de iSéneca | `true` |

---

## Licencia

Esta aplicación se ofrece bajo licencia [AGPL versión 3].

[Symfony]: http://symfony.com/
[Composer]: http://getcomposer.org
[FrankenPHP]: https://frankenphp.dev
[PostgreSQL]: https://www.postgresql.org
[SQLite]: https://www.sqlite.org
[AGPL versión 3]: http://www.gnu.org/licenses/agpl.html
