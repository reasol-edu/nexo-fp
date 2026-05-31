# Registro de cambios

Todos los cambios notables en este proyecto se documentarán en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es/1.1.0/),
y este proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

### Añadido

- `feat(i18n)`: Traducir cadenas del comando app:create-admin
- `feat(command)`: Comando para crear administrador global
- `feat(model)`: Añadir email opcional a Teacher
- `feat(model)`: Company pertenece a EducationalCentre
- `feat(model)`: AcademicYear pertenece a EducationalCentre
- `feat(model)`: Gedmo Loggable en Company, eliminar audit-trail-bundle
- `feat(model)`: Unique constraint stay+student en TrainingPosition
- `feat(model)`: OrphanRemoval en Stay -> TrainingPosition
- `feat(model)`: Los estudiantes pueden pertenecer a más de un grupo
- `feat(model)`: La estancia centraliza las ofertas de puestos formativos
- `feat(model)`: Usar UUIDv7 como identificador en las entidades
- `feat`: Activados lazy objects nativos en el ORM
- `feat`: Incluida fecha inicio y de finalización de un puesto formativo
- `feat`: Activada auditoría de entidades
- `feat`: Modelo de datos inicial
- `docs`: Añadido README.md
- `core`: Añadido componente webprofiler y debug
- `core`: Añadido componente de migraciones de bases de datos y maker-bundle

### Modificado

- `refactor`: Inyectar repositorio tipado y usar métodos named
- `refactor(model)`: Eliminar academicYear de Teacher
- `refactor(model)`: Sustituir roles dinámicos por columna admin en Teacher
- `refactor(model)`: Fusionar User en Teacher como entidad de seguridad
- `refactor(model)`: Embeddable PersonName en Teacher, Worker y Student
- `refactor(model)`: Poner id de solo lectura en todas las entidades
- `refactor(model)`: Fetch EXTRA_LAZY en todas las colecciones
- `refactor(model)`: Los estudiantes no se excluyen
- `refactor(model)`: Eliminar tipo de comentario
- `chore(deps)`: Actualizar componentes a Symfony 8.1
- `chore(deps)`: Instalado componente de auditoría rcsofttech/audit-trail-bundle
- `chore`: Actualizados componentes a la última versión

### Corregido

- `fix(docs)`: Indicar correctamente que se usa Symfony 8.1
- `fix(model)`: Corregida errata en atributo. Rehechas las migraciones
- `fix`: Actualizar dependencias y corregir mapeo UUID tras rebase
