# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `feat`: Sección Administración con CRUD de centros educativos y cursos académicos
- `feat`: Usar logo SVG propio en todas las plantillas en lugar del icono genérico
- `feat`: Cambiar de centro activo desde el sidebar si se tiene acceso a más de uno
- `fix`: Especificar tipo 'uuid' explícito en setParameter para búsquedas por ID
- `fix`: Evitar proxy lazy de AcademicYear cargando activeAcademicYear con JOIN
- `feat`: Selección de centro educativo al iniciar sesión con persistencia en sesión
- `feat`: Crear curso académico automáticamente al crear un centro educativo
- `feat`: Dashboard con barra de navegación lateral y soporte de suplantación
- `feat`: Exponer nombre y versión de la app como parámetros y globales Twig
- `refactor(i18n)`: Separar messages.es.yaml en dominios login y dashboard
- `chore(migrations)`: Rehacer migración con el modelo de datos actual
- `feat(i18n)`: Extraer todas las cadenas de plantillas al dominio messages
- `feat`: Pantalla de login con Tailwind CSS, Asset Mapper y Symfony UX
- `feat`: Sustituir Gedmo Loggable por auditoría propia en Company
- `fix`: Corregir 50 errores de tipado detectados por PHPStan
- `feat`: Instalar PHPStan nivel 6 con extensiones Symfony y Doctrine
- `feat(model)`: Añadir requireActiveAcademicYear() a EducationalCentre
- `feat(model)`: Curso académico activo en EducationalCentre
- `feat(command)`: Comando para crear un centro educativo
- `feat(model)`: Añadir localidad a EducationalCentre
- `feat(model)`: Añadir código de centro a EducationalCentre
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

### Changed

- `chore`: Eliminar hook commit-msg para actualizar CHANGELOG
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

### Fixed

- `fix(docs)`: Indicar correctamente que se usa Symfony 8.1
- `fix(model)`: Corregida errata en atributo. Rehechas las migraciones
- `fix`: Actualizar dependencias y corregir mapeo UUID tras rebase
