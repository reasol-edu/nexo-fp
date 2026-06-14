# Instalación y requisitos

Nexo FP puede ejecutarse de tres formas, según la infraestructura disponible y quién va a utilizarla.

| Modo | Base de datos | Para quién | Esfuerzo |
|------|---------------|------------|----------|
| **Binario nativo** (FrankenPHP) | SQLite | Un centro, sin infraestructura | Mínimo |
| **Docker Compose** | PostgreSQL | Servidor / producción | Medio |
| **Desarrollo local** | PostgreSQL, MySQL/MariaDB o SQLite | Contribuir al proyecto | Para perfiles técnicos |

En los tres casos, las **migraciones de base de datos se aplican automáticamente** al arrancar.

> ¿No tienes conocimientos técnicos y no sabes qué modo elegir? Si solo quieres probarlo o usarlo en tu ordenador, elige
> **Binario nativo**. Busca cómo hacerlo en el capítulo [Despliegue](09-despliegue.md).

## Requisitos

| Modo | Requisitos |
|------|------------|
| Docker | Docker Engine 24+ y Docker Compose v2 |
| Binario nativo | Sin requisitos adicionales (todo incluido) |
| Desarrollo local | PHP 8.4+, Composer, PostgreSQL 16+, MySQL 8+ / MariaDB 11+ o SQLite |

## Inicio rápido (Docker)

El modo recomendado para producción. La imagen incluye [FrankenPHP](https://frankenphp.dev) como
servidor de aplicaciones y usa [PostgreSQL](https://www.postgresql.org) 16 como base de datos.

```bash
cp .env.example .env   # edita APP_SECRET y DB_PASSWORD
docker compose up -d
```

Accede a **http://localhost** con `admin` / `admin`.

La configuración detallada de cada modo (Docker y binario) está en el capítulo
[Despliegue](09-despliegue.md).

## Desarrollo local

Para contribuir al proyecto o ejecutarlo desde el código fuente. Requisitos: PHP 8.4+, Composer y Docker
Compose (solo para la base de datos).

```bash
# 1. Clona el repositorio y copia el entorno
cp .env.example .env          # ajusta si es necesario

# 2. Levanta solo PostgreSQL con el overlay de desarrollo
docker compose -f compose.yaml -f compose.dev.yaml up -d

# 3. Instala dependencias e inicializa la base de datos
composer install
make migrate
php bin/console app:setup

# 4. Arranca el servidor de desarrollo
symfony server:start          # o: php -S localhost:8000 -t public/
```

Accede a **http://localhost:8080** con `admin` / `admin`.

> El overlay `compose.dev.yaml` se combina con `-f` y expone PostgreSQL en el puerto 5432, dejando el
> servicio PHP (`app`) tras el perfil `production`; por eso el comando anterior solo arranca la base de
> datos. En producción se usa únicamente `compose.yaml` (`docker compose up -d`), que levanta también la
> aplicación.

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
