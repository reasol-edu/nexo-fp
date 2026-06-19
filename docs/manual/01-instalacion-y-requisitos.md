# Instalación y requisitos

Nexo FP puede ejecutarse de cinco formas, según la infraestructura disponible y quién va a utilizarla.

| Modo | Base de datos | Para quién | Esfuerzo | Colaboración en tiempo real |
|------|---------------|------------|----------|-----------------------------|
| **Binario nativo** (FrankenPHP) | SQLite | Un centro, sin infraestructura | Mínimo | ✅ |
| **Docker Compose** | PostgreSQL | Servidor / producción | Medio | ✅ |
| **Ubuntu Server 26.04** (FrankenPHP + systemd) | PostgreSQL | VPS Ubuntu, sin Docker | Medio | ✅ |
| **Plesk** (PHP-FPM) | MySQL/MariaDB o PostgreSQL | VPS con Plesk, sin Docker | Medio | ❌ |
| **Desarrollo local** | PostgreSQL, MySQL/MariaDB o SQLite | Contribuir al proyecto | Para perfiles técnicos | ✅ |

En binario nativo, Docker y Ubuntu Server las **migraciones se aplican automáticamente** al arrancar;
en Plesk y desarrollo local hay que ejecutarlas manualmente.

!!! tip "¿No tienes conocimientos técnicos?"
    Elige **Binario nativo**. La guía paso a paso en
    [Prueba rápida en tu ordenador](09-despliegue.md#prueba-rapida-en-tu-ordenador-sin-conocimientos-tecnicos)
    te lleva desde la descarga hasta la aplicación en marcha en tres pasos.

## Requisitos

| Modo | Requisitos |
|------|------------|
| Binario nativo | Sin requisitos adicionales (todo incluido) |
| Docker | Docker Engine 24+ y Docker Compose v2 |
| Ubuntu Server 26.04 | Ubuntu 26.04 LTS, acceso SSH con sudo y un dominio apuntando al servidor |
| Plesk | PHP 8.4 FPM, extensiones estándar, MySQL 8+ / MariaDB 10.6+ (o PostgreSQL 12+), Composer, acceso SSH |
| Desarrollo local | PHP 8.4+, Composer, PostgreSQL 16+, MySQL 8+ / MariaDB 11+ o SQLite |

## Guías de instalación

Las instrucciones detalladas de cada modo están en el capítulo [Despliegue](09-despliegue.md):

- [Prueba rápida en tu ordenador](09-despliegue.md#prueba-rapida-en-tu-ordenador-sin-conocimientos-tecnicos) — sin conocimientos técnicos, con datos de ejemplo listos para explorar
- [Binario nativo](09-despliegue.md#ejecucion-como-binario-nativo) — un solo ejecutable, ideal para un IES sin infraestructura
- [Docker Compose](09-despliegue.md#despliegue-con-docker) — producción con HTTPS automático y base de datos robusta
- [Ubuntu Server 26.04](09-despliegue.md#despliegue-en-ubuntu-server-2604) — script de instalación automatizada, sin Docker
- [Plesk](09-despliegue.md#despliegue-en-plesk) — VPS ya gestionado con Plesk, sin necesidad de Docker
- [Desarrollo local](09-despliegue.md#desarrollo-local) — ejecutar desde el código fuente para contribuir al proyecto
