<p align="center">
  <img src="public/static/logo.svg" alt="Nexo FP" width="120">
</p>

<h1 align="center">Nexo FP</h1>

<p align="center">
  Plataforma web para gestionar la planificación y asignación de las estancias del alumnado en empresas de la FFEOE
</p>

<p align="center">
  <strong>v2.5.0</strong> &nbsp;·&nbsp;
  <a href="https://reasol-edu.github.io/nexo-fp/">Documentación</a> &nbsp;·&nbsp;
  <a href="CHANGELOG.md">Cambios</a> &nbsp;·&nbsp;
  <a href="CONTRIBUTING.md">Contribuir</a> &nbsp;·&nbsp;
  <a href="http://www.gnu.org/licenses/agpl.html">AGPL-3.0</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/licencia-AGPL--3.0-blue" alt="Licencia AGPL-3.0">
  <img src="https://img.shields.io/badge/PHP-8.4+-777bb4" alt="PHP 8.4+">
  <img src="https://img.shields.io/badge/Symfony-8-black" alt="Symfony 8">
</p>

---

<p align="center">
  <img src="docs/manual/img/inicio.png" alt="Panel de inicio de Nexo FP con las métricas del curso y las estancias activas" width="800">
</p>

---

Nexo FP es una aplicación web desarrollada con [Symfony] que permite planificar y preparar la
**Fase de Formación en Empresa u Organismo Equiparado**. Centraliza la
información de estudiantes, empresas, puestos formativos y tutores, y permite llevar el seguimiento
del proceso de asignación desde que se crea un puesto hasta que se registra en Séneca.

La aplicación se ha diseñado para ser intuitiva y fácil de usar, con un enfoque en la eficiencia
y la reducción de errores administrativos. Permite generar informes detallados en PDF y facilita la
comunicación entre el centro educativo y las empresas.

Está pensada para el **trabajo en equipo**: varias personas pueden gestionar los puestos de una misma
estancia a la vez y la pantalla se **actualiza en tiempo real** (sincronización en vivo con Mercure),
resaltando lo que cambia y avisando de los conflictos de edición en lugar de sobrescribir.

Es **multi-centro**: un mismo servidor puede alojar varios centros educativos con datos
completamente separados. Cada docente selecciona el centro activo al iniciar sesión y solo ve los
datos de ese centro. Los administradores globales pueden gestionar todos los centros desde la
sección **Administración**.

> Nexo FP forma parte del proyecto de innovación educativa REASOL (PIN-219/23 y PIN-354/24) financiado
> por la Consejería de Desarrollo Educativo y Formación Profesional de la Junta de Andalucía.

Consulta [CONTRIBUTING.md](CONTRIBUTING.md) para la guía de contribución y [CHANGELOG.md](CHANGELOG.md)
para el historial de cambios.

---

## ¿Solo quieres probarlo?

¿No eres una persona técnica y solo quieres ver cómo funciona Nexo FP en tu propio ordenador? No
necesitas instalar nada ni saber de informática. Entra en la
**[página de descargas](https://github.com/reasol-edu/nexo-fp/releases)**, descarga el archivo de tu
sistema (Windows, Mac o Linux), descomprímelo y haz doble clic en el script de demostración
(`demo.bat` en Windows, `demo.command` en Mac, `demo.sh` en Linux). En unos segundos tendrás la
aplicación funcionando con datos de ejemplo: abre tu navegador en **<http://localhost:8080>** y entra
con el usuario `admin` y la contraseña `admin`. Tienes el paso a paso ilustrado en
[Prueba rápida en tu ordenador](docs/manual/09-despliegue.md#prueba-rápida-en-tu-ordenador-sin-conocimientos-técnicos)
del manual.

---

## Documentación

La documentación detallada vive en el **[manual de Nexo FP](docs/manual/)** (`docs/manual/`), que es la
fuente de referencia principal. Cubre instalación, roles y permisos, el flujo de trabajo completo, la referencia
de cada pantalla, notificaciones, ajustes, comandos de consola y despliegue.

La versión web navegable de la última versión estable está publicada en
**<https://reasol-edu.github.io/nexo-fp/>**.

El manual se redacta en Markdown y se genera en dos formatos con el mismo contenido:

- **PDF**: `make docs-pdf` → `docs/manual/nexo-fp-manual.pdf`.
- **Web navegable** (con buscador): `make docs-web` / `make docs-serve`.

Las versiones publicadas del manual (PDF), la presentación (PDF) y la web navegable (ZIP) se generan
automáticamente en cada release y están disponibles, con el número de versión en el nombre
(`nexo-fp-manual-vX.Y.Z.pdf`, `nexo-fp-presentacion-vX.Y.Z.pdf`, `nexo-fp-manual-web-vX.Y.Z.zip`), entre
los activos del [GitHub Release](https://github.com/reasol-edu/nexo-fp/releases). Los comandos `make`
anteriores sirven para previsualización local.

Capítulos:

| Capítulo | Contenido |
|----------|-----------|
| [Introducción](docs/manual/index.md) | Qué es Nexo FP y cómo usar el manual |
| [Instalación y requisitos](docs/manual/01-instalacion-y-requisitos.md) | Modos de despliegue y requisitos |
| [Primeros pasos](docs/manual/02-primeros-pasos.md) | Preparar el curso académico |
| [Roles y permisos](docs/manual/03-roles-y-permisos.md) | Perfiles y tabla de permisos |
| [Flujo de trabajo](docs/manual/04-flujo-de-trabajo.md) | El recorrido completo de un curso |
| [Secciones de la aplicación](docs/manual/05-secciones-de-la-aplicacion.md) | Referencia de cada pantalla |
| [Notificaciones por email](docs/manual/06-notificaciones-y-email.md) | Avisos automáticos y SMTP |
| [Ajustes](docs/manual/07-ajustes.md) | Configuración jerárquica |
| [Comandos de consola](docs/manual/08-comandos-de-consola.md) | Administración por terminal |
| [Despliegue](docs/manual/09-despliegue.md) | Docker y binario nativo |
| [Operación y mantenimiento](docs/manual/10-operacion-y-mantenimiento.md) | Backups, colas, recordatorios |
| [Resolución de problemas](docs/manual/11-resolucion-de-problemas.md) | Soluciones a las dudas más habituales |
| [Glosario](docs/manual/12-glosario.md) | Términos del manual y de la aplicación |

---

## Inicio rápido

```bash
cp .env.example .env.local            # edita APP_SECRET, DB_PASSWORD y MERCURE_JWT_SECRET
export COMPOSE_ENV_FILES=.env.local   # Compose usará .env.local (no el .env versionado)
docker compose up -d
```

Accede a **http://localhost** con `admin` / `admin`.

Para el resto de modos de despliegue (binario nativo y desarrollo local) y su configuración detallada,
consulta el capítulo [Despliegue](docs/manual/09-despliegue.md) del manual.

---

## Requisitos

| Modo | Requisitos |
|------|-----------|
| Docker | Docker Engine 24+ y Docker Compose v2 |
| Binario nativo | Sin requisitos adicionales (todo incluido) |
| Desarrollo local | PHP 8.4+, Composer, PostgreSQL 16+, MySQL 8+ / MariaDB 11+ o SQLite |

---

## Desarrollo local

Requisitos: PHP 8.4+, Composer y Docker Compose (solo para la base de datos).

```bash
# 1. Clona el repositorio y copia el entorno
cp .env.example .env.local            # ajusta si es necesario
export COMPOSE_ENV_FILES=.env.local   # Compose usará .env.local

# 2. Levanta PostgreSQL y el hub Mercure con el overlay de desarrollo
docker compose -f compose.yaml -f compose.dev.yaml up -d

# 3. Instala dependencias e inicializa la base de datos
composer install
make migrate
php bin/console app:setup

# 4. Arranca el servidor de desarrollo
symfony server:start          # o: php -S localhost:8000 -t public/
```

Accede a **https://localhost:8000** (o **http://localhost:8000** con `php -S`) con `admin` / `admin`.

> **Atajo:** una vez instaladas las dependencias y la base de datos (pasos 1-3), `make dev`
> levanta los contenedores de desarrollo (PostgreSQL + hub Mercure) y arranca `symfony serve`
> de una vez. `make dev-stop` detiene los contenedores. Requiere `.env.local` y la Symfony CLI.

> El overlay `compose.dev.yaml` (que se combina con `-f`) expone PostgreSQL en el puerto 5432 y un hub Mercure (imagen `dunglas/mercure`), y deja los servicios `app` y `worker` tras el perfil `production`, de modo que el comando anterior solo arranca la base de datos y el hub. En producción se usa únicamente `compose.yaml` (`docker compose up -d`), que sí levanta la aplicación con el hub embebido en FrankenPHP.

> **Sincronización en vivo en desarrollo:** `symfony server:start` detecta el contenedor del hub e inyecta solo las variables `MERCURE_URL` y `MERCURE_PUBLIC_URL`, así que la actualización en vivo de la pantalla de estancia funciona sin configurar nada más. El hub de desarrollo se abre con suscripción anónima y CORS al origen del servidor local (es solo para dev: el canal nunca transporta datos, solo un aviso de cambio). Con `php -S` no hay hub ni inyección, así que no hay tiempo real (la app sigue funcionando con normalidad). Si arrancas `symfony server:start` en un puerto distinto del 8000, ajusta `cors_origins` en `compose.dev.yaml`.

### Cargar datos de demostración

```bash
make fixtures
```

Consulta [DEMO.md](DEMO.md) para ver los usuarios, centros y escenarios disponibles.

### Ejecutar los tests

```bash
make test
```

### Análisis estático

```bash
php vendor/bin/phpstan analyse
```

### Generar la presentación

El proyecto incluye una presentación de introducción a Nexo FP en
[`docs/slides/`](docs/slides/), escrita en [Marp]. Para exportarla a PDF:

```bash
make slides
```

El comando requiere **Node.js** (usa `npx @marp-team/marp-cli`, sin instalación global) y genera
`docs/slides/nexo-fp.pdf`. Cambiando la extensión de salida puedes obtener otros formatos
(`.pptx`, `.html`). Consulta [`docs/slides/README.md`](docs/slides/README.md) para más detalles.

### Generar el manual

El [manual de Nexo FP](docs/manual/) se redacta en Markdown (`docs/manual/`) y se compila a PDF y a una
web navegable:

```bash
make docs-pdf    # PDF -> docs/manual/nexo-fp-manual.pdf
make docs-web    # web -> docs/manual-site/
make docs-serve  # previsualización en http://127.0.0.1:8000
make docs        # PDF + web
```

El PDF requiere **pandoc** y **Node.js** (usa `npx pagedjs-cli`, el mismo motor Chromium que las slides).
La web requiere **MkDocs Material** (`pip install -r docs/manual/requirements.txt`). Consulta
[`docs/manual/README.md`](docs/manual/README.md) para más detalles.

---

## Licencia

Esta aplicación se ofrece bajo licencia [AGPL versión 3].

[Symfony]: http://symfony.com/
[Marp]: https://marp.app
[AGPL versión 3]: http://www.gnu.org/licenses/agpl.html
