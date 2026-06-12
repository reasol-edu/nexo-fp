# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-06-12

### Added
- Recuperación de contraseña olvidada para usuarios con acceso local: el usuario introduce su nombre de usuario y recibe un enlace por el correo que tiene configurado en su cuenta; el enlace es válido 1 hora, expira al usarse y no revela si el usuario existe; los usuarios con acceso externo (Séneca/IdEA) no pueden usar este flujo

### Changed

- Los filtros del listado de estancias (búsqueda, familia profesional, programa y periodos) se recuerdan por centro en el navegador: al volver al listado se restauran automáticamente y el botón de limpiar filtros también borra el estado guardado
- Campana de notificaciones en la cabecera con las tareas pendientes del docente: firmas próximas a vencer (estancias que terminan en los próximos 14 días), puestos sin estudiante, sin tutor académico o sin mentor laboral, y estudiantes sin puesto; cada elemento enlaza con su estancia
- Nuevas gráficas en el panel: barras horizontales con el total, ocupación y firma de plazas por familia profesional, y diagrama de columnas con la evolución mensual de firmas del curso; generadas en el servidor como SVG sin dependencias JavaScript adicionales
- Nueva página de calendario mensual de estancias (LiveComponent): navegación mes a mes, barras de estancia con colores por familia, gestión de carriles para estancias solapadas y badge ámbar con el número de plazas sin firmar al final de cada estancia
- Paleta de búsqueda global accesible con ⌘K / Ctrl+K desde cualquier página: busca estancias, empresas, estudiantes y docentes aplicando los mismos permisos que la barra lateral; resultados en tiempo real con debounce de 250 ms; navegación por teclado con ↑ ↓ Enter y cierre con Esc

### Fixed

- Los jefes/as de departamento de familia profesional ya pueden crear estancias de las enseñanzas de su familia: el botón «Nueva estancia» aparece en el listado y el formulario muestra las enseñanzas correspondientes; el Voter, el repositorio y la plantilla se han actualizado en consecuencia

## [1.4.1] - 2026-06-12

### Fixed

- Los ejecutables binarios fallaban al arrancar porque los scripts de inicio no definían las variables de entorno `MAILER_DSN` y `MAILER_FROM`, requeridas desde la introducción de las notificaciones por email; ahora se definen con valores por defecto (correo desactivado) y pueden sobreescribirse desde el entorno
- En instalaciones SQLite, los ajustes guardados no persistían: la migración original sembraba los IDs de `setting_definition` como texto RFC 4122, pero Doctrine los serializa como binario de 16 bytes al hacer bind vía PDO; el JOIN fallaba silenciosamente y la página siempre mostraba «Por defecto»

## [1.4.0] - 2026-06-11

### Added

- Los ajustes guardados a nivel global o de centro pueden bloquearse para impedir que los niveles inferiores los sobreescriban; al activar el candado, el valor queda fijo para todos los niveles inferiores y aparece deshabilitado en su página de ajustes, indicando qué nivel lo ha bloqueado
- Cuando un ajuste está bloqueado por un nivel superior, el control del nivel inferior muestra el valor fijado por el nivel bloqueante aunque el nivel inferior tenga guardado un valor propio distinto
- Makefile con targets de desarrollo (`fixtures`, `migrate`, `setup`, `test`) para estandarizar los comandos habituales en entorno local

### Fixed

- Las migraciones de MySQL / MariaDB definían las columnas UUID como `CHAR(36)` en lugar de `BINARY(16)`, lo que provocaba un error al insertar datos (`Incorrect string value`) porque Doctrine almacena los UUID en binario de 16 bytes en ese motor
- Error «Valor no válido» intermitente al seleccionar «Por defecto» en los ajustes booleanos, causado por la conversión de tipos de los parámetros de Stimulus («true» llegaba al servidor como «1»)
- Un ajuste bloqueado en su propio nivel ya no puede restablecerse al valor por defecto ni vaciarse

## [1.3.0] - 2026-06-11

### Added

- Sistema de ajustes configurables a tres niveles (global, centro educativo y personal): ajuste de tamaño de página, interruptor maestro de notificaciones por email y configuración individual de cada tipo de notificación; los valores se resuelven en cascada (personal > centro > global > predeterminado)
- Página de ajustes en el perfil del docente (`/perfil/ajustes`), en el hub del centro (`/mi-centro/ajustes`) y en la administración global (`/admin/ajustes`)
- Los ajustes de tipo entero y cadena pueden tener límites de rango (`min_value` / `max_value`); el tamaño de página admite entre 5 y 100 elementos
- Verificación de email con enlace temporal (24 h) al cambiar de dirección de correo: los docentes no administradores reciben un correo al nuevo buzón y el cambio solo se aplica al hacer clic en el enlace; los administradores globales guardan el cambio directamente
- Soporte para MySQL 8 y MariaDB como motor de base de datos, además de PostgreSQL y SQLite; se incluyen migraciones para las tres plataformas

### Changed

- Un grupo puede tener más de un tutor/a dual docente asignado (relación many-to-many); los tutores del grupo pueden gestionarse desde la sección «Centro educativo»
- Las notificaciones por email pueden habilitarse o deshabilitarse individualmente —por tipo de notificación, por docente y por centro— mediante el sistema de ajustes; la variable de entorno `APP_PAGE_SIZE` ya no está en uso (el tamaño de página se configura en los ajustes)

## [1.2.0] - 2026-06-10

### Added

- Dashboard accionable: bloque «Pendientes» con las estancias activas que requieren atención (estudiantes sin puesto, puestos libres, sin tutorías o finalizados sin firmar), accesos rápidos según permisos y tarjetas de métricas enlazadas a sus secciones
- Exportación a CSV (compatible con Excel) de estudiantes, empresas y puestos de estancia, respetando los filtros activos y los permisos de cada rol
- Modo de asignación rápida en el detalle de estancia: muestra los selectores de asignación en todas las filas sin recargas
- Notificaciones de confirmación (toasts) tras las acciones en vivo del detalle de estancia
- Estados vacíos con botón de acción directa en el detalle de estancia y en los listados de estancias y empresas
- Notificaciones por email: aviso al tutor/a dual docente al asignarle un puesto y a los docentes de enlace al crear puestos de su empresa; desactivadas por defecto (`MAILER_DSN=null://null`)
- Comando `app:send-reminders` para enviar recordatorios de puestos pendientes de firma en estancias próximas a finalizar, pensado para cron diario

### Changed

- El README documenta las nuevas características y la configuración del envío de correo

## [1.1.0] - 2026-06-10

### Added

- Los fixtures de demostración incluyen nombres completos de los ciclos formativos (con prefijo CFGM/CFGS)
- Los fixtures generan tres estancias por enseñanza: una pasada (sept. 2025–ene. 2026), una activa (mar.–jun. 2026) y una futura (sept. 2026–ene. 2027), con estudiantes matriculados sin puesto asignado
- Variable de entorno `LOAD_FIXTURES=true` en los scripts de arranque Docker y binario para pre-cargar los datos de demostración automáticamente al iniciar la aplicación

### Changed

- El contador de estudiantes del dashboard muestra únicamente los alumnos de las enseñanzas accesibles al usuario según su rol (administrador global y de centro ven todos; coordinadores, jefes de familia y docentes de grupo ven solo los de sus enseñanzas)

## [1.0.4] - 2026-06-09

### Added

- Los docentes de enlace pueden añadir puestos de formación en las estancias de las empresas a las que están asignados como enlace
- Al editar un docente en el contexto de un centro educativo, se pueden seleccionar los grupos en los que imparte clase mediante un desplegable con autocompletar
- El árbol de oferta académica muestra el número de alumnos y docentes de cada grupo mediante insignias de colores

### Fixed

- Los docentes de enlace solo ven en el listado las estancias en las que su empresa tiene puestos asignados
- Corregidos los permisos del docente de enlace al consultar estancias que tienen alumnos sin puesto asignado

## [1.0.3] - 2026-06-08

### Changed

- Los componentes de listado (estancias, docentes, alumnos, empresas, familias profesionales…) muestran un indicador de carga mientras se actualiza el contenido
- Las tablas de listados de administración permiten desplazamiento horizontal en pantallas estrechas
- Los diálogos de confirmación de borrado se muestran como un panel integrado en lugar del diálogo nativo del navegador
- Los mensajes de notificación se ocultan automáticamente pasados 4 segundos y pueden cerrarse manualmente con el botón ×

### Fixed

- La confirmación de borrado enviaba el formulario de forma inmediata sin esperar a que el usuario confirmara

## [1.0.2] - 2026-06-08

### Added

- Los administradores pueden impersonar a cualquier docente desde el listado de docentes con el botón «Acceder como»
- Los docentes marcados como equipo directivo del centro pueden acceder a la sección «Centro educativo» y gestionar toda su configuración (estudiantes, docentes del curso, familias profesionales)

### Changed

- El desplegable de enseñanzas del filtro de estancias muestra solo las relacionadas con el docente (responsable de familia, tutor de grupo o docente asignado a un grupo); el equipo directivo y los administradores siguen viendo todas
- El desplegable de familias profesionales del filtro de estancias muestra solo las familias de las enseñanzas visibles para el docente; el equipo directivo y los administradores siguen viendo todas

### Fixed

- El autocompletado del docente responsable de una familia profesional ya muestra los docentes del centro al equipo directivo
- El enlace «Volver al usuario original» del sidebar ahora aparece correctamente al impersonar a un usuario
- El enlace «Centro educativo» del sidebar solo se muestra a docentes con acceso a esa sección (administradores globales o equipo directivo del centro activo)
- Los docentes del equipo directivo ya pueden acceder a las páginas de gestión del centro educativo (los componentes Twig de cada sección bloqueaban el acceso con `ROLE_ADMIN` aunque el voter lo concediera)

## [1.0.1] - 2026-06-08

### Fixed

- La página de edición de un empleado ya no falla al intentar cargarse

### Changed

- El número de identificación (DNI, NIE, pasaporte u otro documento) de los empleados se muestra enmascarado en el listado de la empresa, mostrando únicamente los cuatro dígitos centrales según las directrices de la AEPD

## [1.0.0] - 2024-06-07

### Added

- `feat`: Nuevo rol de coordinador de FP dual: puede crear y gestionar las estancias de las enseñanzas que coordina, y tiene acceso a la sección Empresas
- `feat`: El panel de inicio muestra estadísticas del curso: estancias abiertas, puestos formativos y estado de las asignaciones
- `feat`: Pantalla de edición del perfil del docente, accesible desde el menú de usuario
- `feat`: Al crear un administrador desde la consola, la contraseña puede introducirse de forma interactiva si no se especifica en el comando
- `feat`: Generación de informe PDF de estancias
- `feat`: Vista de detalle de estancia como SPA con Symfony UX Live Components: asignar/desasignar puesto y tutores duales actualiza la página sin recarga completa; los diálogos de confirmación de borrado también funcionan tras cada rerender del componente
- `feat`: Asignación rápida de tutores duales desde la fila del alumno: al hacer hover sobre una fila con puesto asignado pero sin tutor dual docente o de empresa, aparecen selectores con autocompletar que asignan el tutor y recargan la página
- `feat`: Asignación rápida de puesto desde la fila del alumno: al hacer clic en «Sin puesto asignado» se despliega un selector filtrado por nivel del alumno con empresa, centro de trabajo y observaciones; al seleccionar, se asigna el puesto y se recarga la página
- `fix`: Añadir `autofocus` al campo centro de trabajo en el formulario de edición de puesto, igual que en los formularios de alta
- `fix`: Pasar la etiqueta «Mostrar:» del filtro de período por el sistema de traducciones
- `feat`: Confirmación de borrado inline (panel con «Sí, eliminar» / «Cancelar») en lugar del diálogo nativo `confirm()` del navegador, tanto para eliminar estancia como para eliminar puesto
- `refactor`: Reordenar campos en el formulario de edición de puesto: estado y firmado suben antes de observaciones para reflejar su mayor frecuencia de uso operativo
- `feat`: Botón «Seleccionar todos / Deseleccionar todos» global en la página de gestión de estudiantes, que actúa sobre todos los grupos visibles (respeta el filtro de búsqueda activo y los estudiantes bloqueados)
- `feat`: Badge de alerta en las cards del índice cuando hay estudiantes inscritos en la estancia sin puesto formativo asignado
- `feat`: Columna «Niveles» en la tabla de puestos formativos de la vista de detalle, con badges por nivel; oculta en pantallas pequeñas
- `feat`: Botón «Limpiar filtros» en el índice de estancias, visible solo cuando hay algún filtro activo (texto, familia, enseñanza o período); resetea todos los filtros en una sola acción
- `fix`: Añadir indicador `*` de campo obligatorio en nombre y fechas de los formularios de estancia, para consistencia con el campo de enseñanza
- `fix`: Restricción dinámica de la fecha de fin: su `min` se sincroniza con la fecha de inicio seleccionada en los formularios de nueva y edición de estancia
- `feat`: Eliminación de puesto formativo individual desde la tabla de puestos, con confirmación y protección CSRF
- `fix`: Eliminar `overflow-hidden` de las tarjetas de formulario en alta de estancia y alta de puesto; el atributo recortaba los dropdowns de Tom Select que usan `position: absolute`
- `feat`: Restricción de firma: el campo «Firmado» solo está disponible cuando el estado del puesto es «Completado»; validación en servidor y deshabilitación dinámica en cliente
- `refactor`: Vista de detalle de estancia rediseñada: sección de alumnado al inicio agrupada por grupo con color de fila (rojo=sin puesto, ámbar=sin firmar, verde=firmado) y badges de advertencia en cabecera; sección de puestos sin asignar al final con columna de observaciones amplia; eliminada la sección de «Estudiantes sin puesto asignado»
- `feat`: Edición de puesto formativo dentro de una estancia: empresa/centro de trabajo (autocompletar con jerarquía empresa/centro), niveles, estudiante (autocompletar con nombre, NIE y grupo), tutor dual docente (autocompletar restringido a docentes que imparten en algún grupo de la enseñanza), tutor dual de empresa (autocompletar filtrado dinámicamente por la empresa del centro de trabajo seleccionado), observaciones, estado y firma
- `feat`: Autocompletar con jerarquía visual empresa/centro de trabajo en el formulario de nuevo puesto formativo: empresa en primer plano (negrita) y centro de trabajo con localidad como subtítulo; la búsqueda indexa empresa, nombre del centro y localidad; el punto indicador de nivel seleccionado cambia a verde con `peer-checked`
- `feat`: Eliminación de estancia con confirmación; se borran primero los puestos formativos (y sus niveles asociados) y luego la inscripción de estudiantes antes de eliminar la estancia
- `feat`: Edición de estancia (nombre, fechas de inicio y fin) para usuarios con permiso de gestión; la enseñanza se muestra como campo de solo lectura y no puede modificarse una vez creada la estancia
- `feat`: Gestión de estudiantes inscritos en una estancia: alta y baja masiva desde una página dedicada, con búsqueda en tiempo real y agrupación por nivel y grupo; solo se pueden inscribir estudiantes de los grupos del programa de la estancia; los estudiantes con puesto formativo asignado no pueden darse de baja
- `feat`: Vista de detalle de la estancia con cabecera (nombre, familia, fechas, badge de estado), franja de estadísticas (estudiantes con puesto, empresas, puestos ocupados/libres) y tabla de puestos formativos (empresa/centro de trabajo, estudiante, tutor dual docente, tutor dual de empresa, estado, firmado)
- `feat`: Sección de estudiantes sin puesto asignado en la vista de detalle, visible solo cuando existe alguno
- `feat`: Alta de puestos formativos en la vista de detalle: empresa/centro de trabajo (obligatorio), niveles (obligatorio, multi-selección), observaciones (opcional) y número de copias a crear; botón visible solo para usuarios con permiso de gestión
- `feat`: Unicidad del nombre de estancia por curso académico (restricción de base de datos + validación en formulario)
- `feat`: Sección «Estancias» con listado en cards, búsqueda por nombre/enseñanza, filtros por familia profesional, enseñanza y período (En curso / Próximas / Pasadas), y formulario de alta
- `feat`: Fechas de inicio y fin en las estancias; las cards muestran las fechas, un badge de estado (En curso, Próxima, Finalizada) y las estancias pasadas aparecen con opacidad reducida
- `feat`: Estadísticas por estancia en las cards: estudiantes con puesto asignado, empresas con puestos, puestos asignados y puestos sin asignar, con porcentajes y código de color
- `feat`: Radio buttons «Acceso por contraseña» / «Acceso vía usuario IdEA (Séneca)» en los formularios de alta y edición de docentes — reemplazan el checkbox de acceso externo y ocultan el campo de contraseña en tiempo real
- `feat`: Importación de docentes de un centro educativo desde CSV de Séneca (columna `Empleado/a` para nombre y `Usuario IdEA` para el usuario); upsert por nombre de usuario; docentes importados marcados como externos por defecto
- `feat`: Importación de asignaciones docente↔grupo desde CSV de Séneca (columnas `Unidad` y `Profesor/a`); informa de docentes o grupos no encontrados
- `fix`: Eliminar opción de administrador global en el alta de docentes desde la sección Centro educativo (fallo de seguridad)
- `feat`: Sección «Docentes del centro» en el hub Centro educativo para gestionar qué docentes pertenecen al curso académico activo
- `feat`: Formulario de alta de docente desde la sección «Docentes del centro» — si el nombre de usuario introducido no existe, redirige a un formulario de registro pre-rellenado que crea al docente y lo añade al curso en una sola operación; nuevo docente marcado como externo por defecto
- `feat`: Listado de docentes del curso con columnas de usuario, correo, badges de roles (Equipo directivo, Admin, Inactivo, Externo) y paginación con búsqueda en tiempo real
- `feat`: Relación ManyToMany entre `Teacher` y `AcademicYear` (tabla `teacher_academic_year`) para asociar docentes a cursos académicos
- `feat`: Nuevo alias de autocomplete `teacher_centre` que filtra los docentes por el año académico activo del centro; los formularios de la sección «Oferta formativa» usan este alias en lugar de `teacher_admin`
- `feat`: Sección «Estudiantes» en el hub Centro educativo con CRUD completo, listado paginado con búsqueda por NIE/nombre/apellidos y filtro por grupo
- `feat`: Importación masiva de estudiantes desde CSV exportado de Séneca (mapeo de columnas por nombre, detección de codificación Windows-1252, upsert por NIE, omisión de filas con matrícula no activa)
- `feat`: Columna de grupo en el listado de estudiantes, filtrada al curso activo
- `feat`: Página hub «Centro educativo» (`/mi-centro`) como punto de entrada con tarjetas para las secciones del centro activo
- `feat`: Vista árbol colapsable con `<details>/<summary>` para la sección «Oferta formativa» (familias → enseñanzas → niveles → grupos), con búsqueda en tiempo real vía Live Component
- `feat`: CRUD completo de familias profesionales, enseñanzas, niveles y grupos anidado bajo el centro educativo activo
- `feat`: Filtrado en tiempo real y paginación sin recarga en los listados de empresas, docentes y centros educativos
- `refactor`: Componentes Twig anónimos (Form/Field, Form/Textarea) y Live Components (WorkcenterForm, WorkerForm) en la sección Empresas
- `feat`: Selector de docentes de enlace filtrado por equipo directivo y docentes de grupos del centro
- `feat`: Sección Empresas con CRUD de empresas y centros de trabajo, paginación y Voter de seguridad por roles
- `feat`: CIF/NIF obligatorio y único por centro educativo en las empresas
- `feat`: Gestión de empleados asociados a la empresa con vinculación por DNI/NIE
- `feat`: Creación automática de centro de trabajo «Sede Principal» al crear una empresa
- `feat`: Acceso a la sección Empresas para docentes de enlace y jefes de familia profesional
- `feat`: Implementación inicial de paginación
- `feat`: El campo de contraseña se oculta dinámicamente al activar la autenticación externa en el formulario de docente
- `feat`: No es necesario especificar contraseña al crear un docente con autenticación externa activada
- `feat`: Un administrador no puede eliminarse, desactivarse ni quitarse los permisos de administrador a sí mismo
- `feat`: Se puede entrar en la aplicación con el usuario IdEA de Séneca/Pasen
- `feat`: Ahora se puede añadir al equipo directivo en la administración de centros
- `feat:` Sección Administración de docentes
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

- `chore`: La lista de docentes de enlace de una empresa muestra ahora el nombre y apellidos del docente
- `chore`: Mejorada la apariencia de la página de inicio de sesión
- `chore`: Renombrado el apartado de «Familias profesionales» a «Oferta formativa» en toda la UI y traducciones
- `refactor`: Breadcrumbs de oferta formativa actualizados para reflejar la jerarquía «Centro educativo → Oferta formativa → …»
- `refactor`: Enlace a oferta formativa movido desde la barra lateral directamente al hub «Centro educativo»
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

- `fix`: Los docentes podían ver y acceder a estancias de enseñanzas en las que no tenían ninguna atribución
- `fix`: Los responsables de familia profesional no podían gestionar las estancias de las enseñanzas de su familia
- `fix`: El curso académico activo podía no seleccionarse correctamente al iniciar sesión
- `fix`: Los iconos de la interfaz no se mostraban en la instalación nativa
- `fix`: Los scripts de inicio de la instalación nativa no arrancaban correctamente en algunos sistemas
- `fix`: Las estadísticas de puestos y estudiantes en las cards y en la vista de detalle de estancia no reflejaban los datos reales; causa: `WHERE stay IN (:array)` genera un único `IN (?)` que Doctrine no expande correctamente para UUIDs binarios en MySQL; corregido con condiciones OR individuales (`stay.id = :sid_N`) y tipo `'uuid'` explícito por parámetro
- `fix`: Las fechas de inicio y fin de una estancia son obligatorias (columnas `NOT NULL` en base de datos)
- `fix`: Terminología unificada: «tutor dual docente» (tutor académico) y «tutor dual de empresa» (workplaceMentor) en toda la sección de estancias
- `fix`: Botones «Editar» en la vista árbol no navegaban al usar `preventDefault` en el wrapper del botón; corregido con `stopPropagation`
- `fix`: Sección «Administración» se marcaba activa en el sidebar al navegar por oferta formativa; corregido con matching explícito de prefijos de ruta por ítem de navegación
- `fix`: Eliminar centros de trabajo en cascada al eliminar una empresa
- `fix`: Localidad obligatoria en centros de trabajo (migración y modelo)
- `fix(i18n)`: Corregidas algunas traducciones
- `fix(docs)`: Indicar correctamente que se usa Symfony 8.1
- `fix(model)`: Corregida errata en atributo. Rehechas las migraciones
- `fix`: Actualizar dependencias y corregir mapeo UUID tras rebase
