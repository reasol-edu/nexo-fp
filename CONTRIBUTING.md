# Cómo contribuir a Nexo FP

El repositorio oficial se encuentra en [github.com/reasol-edu/nexo-fp](https://github.com/reasol-edu/nexo-fp).

## Reportar un problema o proponer una mejora

Usa el [gestor de incidencias de GitHub](https://github.com/reasol-edu/nexo-fp/issues). Antes de abrir una, comprueba que no exista ya una similar.

Al reportar un error, incluye los pasos para reproducirlo, el comportamiento esperado y el observado, y la versión de la aplicación.

## Enviar cambios

1. Haz un *fork* del repositorio y crea una rama a partir de `main`.
2. Realiza los cambios siguiendo las convenciones del proyecto.
3. Comprueba que los tests pasan: `php bin/phpunit`.
4. Abre una *pull request* describiendo los cambios y referenciando las incidencias relacionadas.

### Plantilla de pull request

GitHub pre-rellena el formulario con `.github/PULL_REQUEST_TEMPLATE.md`. La plantilla incluye:

- **Descripción** — qué cambia y por qué.
- **Tipo de cambio** — casillas para marcar el tipo (`feat`, `fix`, `chore`…) y si contiene cambios rupturistas.
- **Incidencias relacionadas** — `Closes #N` o `Refs #N`.
- **Checklist** — tests pasando, tests actualizados y `CHANGELOG.md` actualizado si el cambio es visible para el usuario.

### Plantillas de incidencia

GitHub ofrece dos plantillas al abrir una incidencia:

- **Error en la aplicación** — solicita una descripción del problema, los pasos para reproducirlo, la versión y el modo de despliegue.
- **Propuesta de mejora** — solicita el problema que resuelve, el rol más beneficiado y la solución propuesta.

---

## Mensajes de commit

El formato de cada commit es:

```
<tipo>[(<ámbito>)][!]: <descripción breve en español>

[cuerpo opcional]

[Closes #N | Refs #N]
```

La descripción empieza en minúscula y no supera los 70 caracteres.

### Tipos

| Tipo | Cuándo usarlo |
|------|---------------|
| `feat` | Nueva funcionalidad visible para el usuario, incluida la adición de nuevos campos o entidades al modelo |
| `fix` | Corrección de comportamiento incorrecto o inesperado |
| `chore` | Mantenimiento sin impacto funcional: actualizaciones de dependencias, ajustes de configuración, scripts |
| `refactor` | Reestructuración de código sin cambio de comportamiento observable, incluida la reorganización del modelo existente |
| `test` | Cambios exclusivos en la batería de pruebas (sin tocar código de producción) |
| `docs` | Cambios exclusivos en documentación (README, CHANGELOG, comentarios…) |

La distinción clave entre `feat` y `refactor` aplicada al modelo:

- **`feat(model)`** — se añade una entidad, campo o relación nueva que amplía lo que el sistema puede representar.
- **`refactor(model)`** — se reorganiza o renombra lo que ya existe sin ampliar capacidad.

### Cambios importantes que requieren atención especial

Añade `!` inmediatamente después del tipo (y del ámbito, si lo hay) cuando el cambio sea incompatible con versiones anteriores: migraciones que alteran columnas existentes, cambios en la firma de comandos de consola, modificaciones en el esquema que requieren pasos manuales al desplegar.

```
feat(model)!: cambiar tipo de la columna status a enum nativo de PostgreSQL
fix!: el comando app:create-admin ahora exige especificar el nombre de usuario
```

### Ámbitos opcionales

El ámbito indica la capa técnica o el área de la aplicación afectada. Se pueden combinar separados por `/` cuando el cambio cruza varias dimensiones.

#### Capas técnicas

| Ámbito | Capa |
|--------|------|
| `model` | Entidades y modelo de dominio |
| `migrations` | Migraciones de base de datos |
| `command` | Comandos de consola |
| `i18n` | Traducciones e internacionalización |
| `dist` | Scripts o configuración de distribución y *build* |
| `ci` | Configuración de integración continua |
| `deps` | Dependencias del proyecto |

#### Dominios de la aplicación

| Ámbito | Sección |
|--------|---------|
| `stays` | Estancias y puestos formativos |
| `companies` | Empresas, centros de trabajo y empleados |
| `centre` | Centro educativo: docentes, estudiantes y oferta formativa |
| `admin` | Administración global |

### Referencias a incidencias

Cuando un commit resuelve o está relacionado con una incidencia de GitHub, inclúyelo en el pie del mensaje:

- `Closes #N` — cierra la incidencia automáticamente al hacer merge.
- `Refs #N` — la referencia sin cerrarla (útil en commits parciales).

### Ejemplos

```
feat(stays): filtro por estado en el listado de puestos formativos

Closes #42
```

```
fix(companies): los docentes de enlace no podían editar centros de trabajo

Refs #38
```

```
feat(model)!: cambiar tipo de la columna status a enum nativo de PostgreSQL

Requiere ejecutar la migración manualmente antes de arrancar la aplicación.

Closes #51
```

```
chore(deps): actualizar Symfony a 8.2
refactor(stays/i18n): unificar cadenas de estado de puesto en un solo dominio
test(centre): cubrir el caso de importación con CSV en codificación Windows-1252
docs: documentar el modo de despliegue con Docker en el README
```

---

## CHANGELOG

Los cambios visibles para el usuario se documentan en la sección `[Unreleased]` de `CHANGELOG.md`, siguiendo [Keep a Changelog](https://keepachangelog.com/en/1.1.0/):

- Las cabeceras de sección (`Added`, `Changed`, `Fixed`…) van en **inglés**.
- El contenido de cada entrada va en **español**, dirigido al usuario de la aplicación y sin tecnicismos.
- Las entradas nuevas se añaden **al principio** de su sección.
- Los commits rupturistas (`!`) deben tener entrada en `Fixed` o `Changed` según corresponda, indicando si se requiere algún paso manual al actualizar.
- Los cambios internos (`ci`, `test`, `docs`, `refactor` sin impacto visible) **no requieren entrada** en el CHANGELOG.
