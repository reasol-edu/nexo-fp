# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.5.0] - 2026-06-22

### Changed

- Rediseñada la gestión de la **oferta formativa** en una única pantalla con **navegación en columnas** (familias → enseñanzas → niveles → grupos): al seleccionar un elemento se despliega la columna siguiente sin recargar la página, con un buscador para localizar familias en ofertas grandes
- El elemento seleccionado se **crea, edita y elimina en el mismo panel**: las asignaciones de personal (responsable de familia, coordinadores/as, tutores/as y docentes) se guardan al instante, y el nombre y las observaciones se confirman con «Guardar cambios»; el borrado pide confirmación en dos pasos
- En el detalle de grupo, las observaciones pasan al final del formulario, los tutores/as ocupan su lugar junto al nombre y los docentes disponen de una línea propia
- Los desplegables de personal del panel filtran sobre la lista local (muestran todas las opciones y filtran desde la primera letra); se corrige el texto de ayuda, que antes indicaba un mínimo de dos caracteres propio de la búsqueda remota de las pantallas anteriores

### Removed

- Eliminadas las pantallas independientes de alta y edición de familias, enseñanzas, niveles y grupos, ahora redundantes con la edición integrada en la pantalla de oferta formativa

## [2.4.5] - 2026-06-21

### Fixed

- Los desplegables TomSelect del registro de actividad y del listado de estancias se renderizan ahora en `<body>` (`dropdownParent: "body"`) para evitar que los contenedores con `overflow-hidden` los recorten
- El filtro de búsqueda del listado de estancias ocupa un tercio del espacio junto a los desplegables de familia profesional y enseñanza (proporción 1:1:1); se corrige además la anchura de los desplegables TomSelect que no respetaban el contenedor flex

## [2.4.4] - 2026-06-21

### Added

- Botón para exportar a Excel el listado de puestos formativos pendientes de firma desde la pestaña «Firmas pendientes», respetando los filtros activos (búsqueda, familia profesional, enseñanza y período)

### Fixed

- Los desplegables de filtro del registro de actividad (Centro, Curso y Tipo de acción) y del listado de estancias (Familia profesional y Enseñanza) ahora usan TomSelect, unificando su aspecto con el resto de selectores de la aplicación

## [2.4.3] - 2026-06-21

### Changed

- La imagen Docker oficial se publica en `reasoledu/nexo-fp` (Docker Hub); `compose.yaml` la usa directamente, por lo que `docker compose up` ya no requiere compilar la imagen localmente

## [2.4.2] - 2026-06-21

### Changed

- Eliminada la variable de entorno `APP_PAGE_SIZE` de todos los scripts de despliegue y ficheros de configuración; el tamaño de página ya se gestiona exclusivamente desde los ajustes de la aplicación (base de datos)
- Añadidas `APP_LOG` y `APP_LOG_RETENTION_DAYS` al `.env` generado por los scripts del binario nativo (`start.sh`, `start.bat`, `start.ps1`) y del instalador Ubuntu, donde faltaban

## [2.4.1] - 2026-06-21

### Added

- Páginas de error personalizadas (404, 403, 500 y fallback genérico) con diseño coherente con la guía de estilo de la aplicación: fondo oscuro plum, orbes decorativos con efecto parallax que siguen el puntero del ratón. La 404 presenta el logotipo inscrito en el «0» con un anillo de gradiente animado; la 403 muestra un escudo que se repele al acercar el cursor; la 500 aplica un efecto de glitch al logotipo

### Fixed

- Añadidos los iconos `arrow-left.svg` y `user-circle.svg` al repositorio (se usaban en plantillas pero no estaban versionados, lo que causaba fallos en `ux:icons:warm-cache` en instalaciones limpias)
- PHPStan (nivel 8): corregidas las anotaciones de tipo `array` sin valor genérico en la entidad, mensaje, servicio y suscriptor del registro de actividad, y en `StudentController::buildImportFlash()` y `OfertaFormativaImporter::uniqueUsernames()`; eliminado el `assert(instanceof Teacher)` redundante en `ActivityLogController`
- Tests de unidad: sustituidos `createMock()` por `createStub()` en los repositorios de `OfertaFormativaExporterTest` e `OfertaFormativaImporterTest` que no configuraban expectativas; añadido `expects(self::once())` en las llamadas `with()` que lo requerían; añadido `#[AllowMockObjectsWithoutExpectations]` en `OfertaFormativaImporterTest` para los mocks de uso mixto. La suite de 1004 tests termina ahora sin notices ni deprecaciones

## [2.4.0] - 2026-06-21

### Added

- Los **administradores globales y de centro** pueden visualizar datos de cursos académicos anteriores sin cambiar el curso activo: en la barra lateral aparece un icono de calendario junto al nombre del curso que abre un selector. Durante la sesión, el panel de inicio, el listado de estancias, el calendario y la búsqueda global muestran los datos del curso histórico seleccionado. Un banner ámbar recuerda en todo momento qué curso se está viendo y permite volver al activo con un clic. El cambio es de sesión y no afecta al resto de docentes
- Todas las **operaciones de escritura** (crear, editar, eliminar, importar) quedan bloqueadas cuando se visualiza un curso histórico: los botones desaparecen de la interfaz y cualquier acceso directo devuelve un error de acceso denegado. Afecta a estancias, estudiantes, docentes del curso y familias/enseñanzas; las acciones de solo lectura (ver, exportar, informes) siguen disponibles
- **Registro de auditoría de actividad**: los administradores globales pueden consultar un historial de acciones desde la sección Administración. Se registran inicios y cierres de sesión, suplantaciones de usuario, operaciones de escritura y exportaciones de datos. Cada entrada incluye fecha/hora, IP, usuario activo, usuario real (en suplantaciones) y centro/curso afectados. El listado dispone de filtros en tiempo real (rango temporal con accesos rápidos, autocompletar de usuario, centro, curso y tipo de acción), columnas ordenables y paginación. La captura es asíncrona para no penalizar el rendimiento. Las entradas se purgan automáticamente cada semana según el período de retención configurable (`APP_LOG_RETENTION_DAYS`, 90 días por defecto)

## [2.3.10] - 2026-06-20

### Fixed

- Seguridad: el listado de estancias aceptaba UUIDs de familia profesional y de enseñanza con formato válido pero ajenos al centro activo. La consulta los filtraba por año académico (no había leak de datos), pero servía como **oráculo de confirmación** sobre IDs concretos. Ahora `StayListComponent::mount()` valida que el UUID exista en el centro activo (vía `ProfessionalFamilyRepository::findByYearAndId` y `ProgrammeRepository::findByAcademicYearAndId`) y, si no, lo limpia. Cambiar de familia también limpia la enseñanza si ya no coincide

## [2.3.9] - 2026-06-20

### Added

- Política mínima de contraseña: el cambio desde Mi perfil y el restablecimiento por email exigen **al menos 12 caracteres**, con pista visible en el formulario y mensaje de error explícito (centralizado en el servicio `PasswordPolicy`)
- Cabeceras de seguridad HTTP en los tres despliegues (Docker/Caddy, binario nativo y Apache/Plesk): `Strict-Transport-Security`, `X-Content-Type-Options`, `Referrer-Policy` y `Permissions-Policy`; también se elimina la cabecera `Server` (y `X-Powered-By` en Apache)

### Changed

- Accesibilidad: pestañas «Estancias» / «Firmas pendientes» implementan el patrón WAI-ARIA (`tablist`/`tab`/`tabpanel`, `aria-selected`, `aria-controls`, roving `tabindex`); las cabeceras ordenables de la tabla de firmas pendientes declaran `aria-sort` y un texto de ayuda para lectores de pantalla; el botón de mostrar/ocultar contraseña en el login y en el restablecimiento incluye `aria-label`/`aria-pressed`/`aria-controls` y el controlador Stimulus mantiene `aria-pressed` sincronizado con el estado visible
- Mensajes flash (éxito/error y *toasts* de acciones en vivo en el detalle de estancia) anunciados a lectores de pantalla con `role="status"` y `aria-live` (assertive si hay error, polite en el resto), `aria-atomic="true"`
- Enlace de cerrar sesión: incluye el token CSRF en la URL (`csrf_token('logout')`) para alinearse con `csrf_protection.stateless_token_ids: [logout]` activado en `csrf.yaml`
- El secreto del hub Mercure (`MERCURE_JWT_SECRET`) deja de tener un valor por defecto inseguro en `.env`. En desarrollo local hay que añadirlo a `.env.local` (un comando listo para copiar en el manual); en los despliegues de binario y Docker se sigue generando automáticamente. Plesk ya requería configurarlo
- Refactor menor en `StayListComponent.html.twig`: las macros `sortIcon`/`ariaSort` se mueven al inicio del template para que su ámbito quede claro

### Fixed

- Seguridad: enumeración de cuentas por timing en `POST /contrasena/recuperar`. El envío síncrono del correo de restablecimiento permitía distinguir «usuario existe» (latencia de SMTP) de «no existe» (retorno inmediato) pese al mensaje neutro. Ahora ambas ramas tardan un mínimo común (900 ms en prod, 0 en tests) midiendo el tiempo y rellenando con `usleep`; el parámetro `app.password_reset.request_min_duration_us` controla el umbral
- Seguridad: el cambio de email en Mi perfil rechaza la dirección si ya la usa otra cuenta como email principal o como `pendingEmail` (comparación insensible a mayúsculas). Antes el `flush` dejaba dos docentes con el mismo correo, rompiendo notificaciones y verificación

## [2.3.8] - 2026-06-20

### Fixed

- Seguridad: XSS almacenado en el historial de empresas. El campo «Observaciones» (`exceptional_circumstances`) se renderizaba con `|raw` en la pestaña Historial de cambios pese a ser un textarea de texto plano (sin sanitizado HTML). Un usuario con permiso de edición de empresa podía inyectar JavaScript que se ejecutaba al abrir el historial por cualquier otro editor de esa empresa. Ahora se renderiza escapado como cualquier otro campo de texto

## [2.3.7] - 2026-06-20

### Added

- Documentación de **despliegue continuo (CD)** en Ubuntu Server: script de actualización automática compatible con etiquetas reescritas (`git push --force`), y dos estrategias de activación — sondeo periódico con systemd timer (sin puertos extra) y webhook de GitHub (actualización instantánea al publicar una release), con instrucciones para proxy inverso sobre el puerto 443 existente

## [2.3.6] - 2026-06-20

### Added

- Pestaña **Firmas pendientes** en el listado de estancias: muestra los puestos formativos registrados en Séneca que aún no tienen el convenio firmado, ordenados por fecha fin ascendente (el más urgente primero). Los mismos filtros de búsqueda (incluyendo nombre de grupo), familia profesional, enseñanza y período que el listado de estancias se aplican a esta pestaña. Una insignia numérica indica cuántos puestos están pendientes con los filtros activos. Los resultados respetan los permisos por rol del usuario; en pantallas pequeñas se presentan como tarjetas. La fecha fin se colorea en rojo (≤ 7 días) o ámbar (≤ 30 días) para priorizar de un vistazo

## [2.3.5] - 2026-06-20

### Added

- Soporte de despliegue en servidores Apache/Plesk: fichero `.htaccess` estándar de Symfony incluido en el repositorio (vía `symfony/apache-pack`) para que el enrutamiento funcione sin configuración adicional de servidor
- Documentación de despliegue: secciones «Actualizar a una nueva versión» añadidas para el modo binario nativo y para desarrollo local, completando la cobertura de todos los modos de despliegue

### Changed

- Datos de demostración mejorados: Audiología Protésica (AP) sustituye a Cuidados Auxiliares de Enfermería; dos apellidos en todos los estudiantes y trabajadores; grupos de mañana y tarde en DAW (1ºDAW-M, 1ºDAW-T, 2ºDAW-M, 2ºDAW-T con menos alumnos por la tarde); una posición sin tutor docente para ilustrar el bloque «Pendientes»; posición con fecha próxima a vencer para la campana de notificaciones; información de contacto enriquecida en varias empresas

## [2.3.4] - 2026-06-20

### Changed

- Manual de usuario revisado: introducción reescrita para centrar el objetivo de la aplicación (planificación y asignación de puestos formativos) y dejar claro que el acceso es exclusivo para el profesorado; eliminada la mención al framework en el primer párrafo; corregidas todas las referencias a exportación («CSV») por «Excel» en el texto, las tablas de permisos, el glosario de acciones y la presentación
- Presentación revisada: eliminados nombres de tecnologías internas de las diapositivas de funcionalidad; mención al modo de despliegue Ubuntu Server añadida en la diapositiva de sincronización en tiempo real
- Mejoras de estilo en el manual: eliminadas expresiones poco naturales, repeticiones léxicas cercanas y calcos del inglés («con un enfoque en»)

## [2.3.3] - 2026-06-20

### Added

- Exportación de listados a Excel (`.xlsx`): los botones de «Exportar» en empresas y en la pantalla de estancia generan ahora un fichero Excel real en lugar de CSV; la fila de cabecera aparece en negrita y el fichero es directamente abrible en Excel, Numbers y LibreOffice Calc sin pasos adicionales

### Changed

- Importación de estudiantes en dos pasos: al subir el CSV ahora se muestra una pantalla de vista previa con el recuento de altas, actualizaciones y omisiones (y aviso de grupos no encontrados) antes de confirmar; el fichero se guarda temporalmente y se enlaza a la sesión mediante un UUID para evitar doble envío o manipulación. La vista previa incluye checkboxes para seleccionar qué grupos se importan (todos marcados por defecto); los estudiantes sin grupo reconocido se incluyen siempre
- Aviso de exportación cambiado de «Generando el archivo CSV…» a «Generando el archivo Excel…»
- Pantalla de edición de estancia adaptable a móvil: el listado de estudiantes se transforma en tarjetas apiladas en pantallas pequeñas (igual que el resto de listados del sistema); en tarjeta se muestran todas las columnas (empresa, tutor docente, tutor laboral, estado), los desplegables de asignación rápida siguen funcionando y la sincronización en tiempo real no se ve afectada

## [2.3.2] - 2026-06-19

### Added

- Exportación e importación de la oferta formativa en JSON: familias profesionales, enseñanzas, niveles y grupos, con los nombres de usuario de los docentes asignados. La importación es idempotente (actualiza por nombre, no duplica), permite elegir con checkboxes qué asignaciones de docentes importar (jefes/as de departamento, docentes y tutores de grupo) y notifica los usuarios no encontrados en el servidor de destino
- Tests unitarios completos para `OfertaFormativaExporter` y `OfertaFormativaImporter`

### Changed

- Eliminada la exportación CSV de estudiantes (no era necesaria)

## [2.3.1] - 2026-06-19

### Changed

- Enlace al repositorio añadido en la documentación web

## [2.3.0] - 2026-06-19

### Added

- Admonitions en la documentación: avisos tipo `tip`, `info`, `warning`, `danger`, `note` y `question` en el manual de usuario, tanto en la web (MkDocs Material) como en el PDF (Pandoc + Paged.js). El filtro Lua `pandoc-admonitions.lua` convierte la sintaxis de MkDocs al HTML estructurado que consume el CSS de impresión; los tipos colapsables (`???`) se renderizan expandidos en PDF
- Iconos/emoji en los admonitions del PDF: cada tipo muestra un icono identificativo (💡 tip, 📝 note, ℹ️ info, ⚠️ warning, 🚨 danger, ❓ question) mediante pseudo-elementos CSS `::before`
- Logo de la aplicación en la portada de la documentación web (MkDocs) y en la portada de la presentación Marp

### Changed

- Capturas de diapositivas 9 y 12 retomadas a 1024 px de viewport para que las imágenes de docentes y estudiantes muestren los botones de importación sin recorte

## [2.2.1] - 2026-06-17

### Added

- Ordenación por columna en los listados de estudiantes, empresas y centros: las cabeceras de las columnas ordenables son ahora botones que alternan ascendente/descendente y reinician la paginación, con indicación accesible de la dirección de orden (`aria-sort`) y un icono de estado
- «Ir a la página» en la paginación de los listados: junto a los botones de navegación se añade un campo numérico para saltar directamente a una página; el valor se ajusta automáticamente al rango válido (1 … última página)
- Aviso de generación al exportar a CSV: al pulsar «Exportar» se muestra una notificación no bloqueante («Generando CSV…») que confirma que la descarga se está preparando y desaparece sola
- Foco visible global coherente: enlaces y botones muestran un anillo de foco con la paleta «plum» al navegar con teclado (`:focus-visible`), respetando la preferencia de movimiento reducido

### Changed

- Listados adaptables a móvil: las tablas de estudiantes, empresas, puestos, centros y docentes se transforman en tarjetas apiladas en pantallas pequeñas (cada celda muestra su etiqueta), eliminando el desplazamiento horizontal y manteniendo pulsables las acciones por fila
- Conflicto de edición no destructivo: cuando otra persona modifica un puesto formativo mientras se edita, el formulario conserva los datos tecleados y muestra un aviso en línea con la opción de descartar los cambios y cargar la versión actual, en lugar de redirigir descartando lo escrito
- Tras un error de validación, el foco salta automáticamente al primer campo inválido y se desplaza hasta él; los campos con error se marcan con `aria-invalid` y su mensaje se asocia mediante `aria-describedby`
- Diálogo de confirmación de borrado accesible: el aviso de confirmación se presenta como diálogo modal (`role="dialog"`, foco atrapado, cierre con Escape o clic fuera y retorno del foco al elemento que lo abrió)
- Mejora de contraste del texto secundario y de los estados deshabilitados en listados y paginación, para cumplir el nivel AA de las pautas de accesibilidad (WCAG)
- Realimentación de carga en los listados en vivo: durante las recargas se aplica una animación de pulso atenuado a la zona afectada, respetando la preferencia de movimiento reducido

## [2.2.0] - 2026-06-17

### Added

- Sincronización en vivo de la pantalla de estancia: cuando varias personas trabajan a la vez sobre los puestos formativos de una misma estancia (crear, asignar estudiante, asignar tutor docente o laboral, cambiar de estado, firmar o eliminar), todas las pantallas abiertas de esa estancia se actualizan solas en menos de un segundo, sin necesidad de recargar. Se apoya en Mercure y sigue un diseño «aviso + re-render»: el canal solo transporta una señal vacía de cambio; cada navegador vuelve a pedir al servidor el contenido, que se renderiza aplicando de nuevo los permisos de cada usuario. Así nunca viajan datos ni HTML por el canal y no hay fugas de información entre roles
- Resaltado visual de los cambios externos: al actualizarse la pantalla por la acción de otra persona, las filas cuyo contenido ha cambiado (o las nuevas) se marcan con una animación breve para dar contexto de qué se ha modificado; respeta la preferencia del sistema de movimiento reducido
- Bloqueo optimista en los puestos formativos: el formulario de edición a página completa detecta si otra persona guardó cambios mientras se editaba y avisa en lugar de sobrescribir silenciosamente, pidiendo revisar los datos actualizados antes de volver a guardar

### Changed

- El hub de Mercure va embebido en FrankenPHP en ambos despliegues (Docker y binario nativo); no hace falta ningún servicio ni contenedor adicional. Se añaden las variables `MERCURE_URL`, `MERCURE_PUBLIC_URL` y `MERCURE_JWT_SECRET`; en el binario nativo el secreto se genera automáticamente en el primer arranque (`data/.mercure_secret`)

## [2.1.0] - 2026-06-16

### Added

- Aviso diario de firma pendiente, multi-destinatario: por cada puesto formativo en estado «Registrado en Séneca» que aún no esté firmado y cuya estancia comience dentro de los próximos X días, se envía un recordatorio a todas las personas con responsabilidad sobre el puesto —el tutor dual docente, la coordinación de FP dual de la enseñanza y la jefatura de la familia profesional—. El mensaje se personaliza y agrupa por persona (un único correo diario), listando las estancias y, por estancia, sus estudiantes con el centro de trabajo y los tutores asignados. El alcance depende del rol: quien es solo tutor ve únicamente sus estudiantes; coordinación y jefatura de familia ven todos los de su área de responsabilidad
- Nuevo ajuste configurable `email.notification.signature_reminder.days` (global y por centro, valor por defecto 7, rango 1–365) que fija los días de antelación con los que se empieza a avisar
- Programación automática del aviso mediante el componente Symfony Scheduler (disparo diario a las 8:00), ejecutado por el mismo worker de Messenger que ya envía los correos, tanto en Docker como en el binario nativo. Ya no es necesario configurar un cron externo del sistema operativo
- Seguimiento del último aviso enviado por estancia (`last_signature_reminder_sent_at`), que además garantiza la idempotencia diaria: si el planificador recupera un disparo perdido, las estancias ya avisadas hoy no se vuelven a notificar

### Changed

- El recordatorio de firma pasa de basarse en la fecha de fin de la estancia (un único aviso a los tutores) a basarse en la fecha de inicio, con avisos diarios hasta que el puesto se firme —incluso si la estancia ya ha comenzado— para priorizar que todo esté firmado antes de empezar
- El comando `app:send-reminders` se mantiene para ejecución manual y usa el ajuste por centro; la opción `--days` ahora es opcional y, si se indica, sobre-escribe ese valor

## [2.0.3] - 2026-06-16

### Changed

- Guía rápida en el README para usuarios sin conocimientos técnicos: un párrafo antes de «Documentación» que explica cómo descargar el binario, arrancarlo con datos de ejemplo y acceder a la aplicación, enlazando al paso a paso del manual
- Captura del panel de inicio del manual (`docs/manual/img/inicio.png`) regenerada con la versión actual

## [2.0.2] - 2026-06-16

### Changed

- Mejora general de la documentación: se corrigen los enlaces entre secciones del manual en PDF; se añaden los capítulos de resolución de problemas (FAQ) y glosario; se documenta el acceso con Séneca/iSéneca, el formato del CSV de importación de estudiantes, la nota de protección de datos (RGPD) y la referencia unificada de variables de entorno; se incorpora el aviso de cambio de la contraseña por defecto `admin`/`admin`; se añaden capturas al capítulo de flujo de trabajo, insignias y captura destacada al README; y se corrige la numeración de los pasos y se añaden las URLs de recursos en la presentación

### Fixed

- En el despliegue con Docker, el contenedor calentaba la caché (`cache:warmup`) pero no la regeneraba, y al persistir en el volumen `./data/var` quedaba obsoleta tras actualizar la imagen
- En el despliegue con Docker no se entregaban los correos asíncronos (verificación de email y notificaciones de tutoría/firma): el `compose.yaml` no incluía ningún consumidor de la cola, por lo que los mensajes quedaban encolados sin enviarse. Se añade un servicio `worker` dedicado que ejecuta `messenger:consume` de forma continua y se reinicia automáticamente
- Eliminado un aviso de obsolescencia de Symfony 8.1 al arrancar: los rate limiters de recuperación de contraseña y de búsqueda se inyectaban confiando solo en el nombre del parámetro; ahora se seleccionan con el atributo `#[Target]`

## [2.0.1] - 2026-06-16

### Added

- Scripts de demostración en el paquete del binario nativo (`demo.sh`, `demo.command`, `demo.bat` y `demo.ps1`) que arrancan la aplicación con los datos de ejemplo ya cargados, equivalentes a los `start.*` con `LOAD_FIXTURES=true`. En macOS, `demo.command` puede abrirse con doble clic desde el Finder
- Guía «Prueba rápida en tu ordenador (sin conocimientos técnicos)» en el capítulo de despliegue del manual, con una tabla de qué archivo descargar según el equipo, tres pasos para arrancar con datos de ejemplo, capturas de la página de Releases y de la pantalla de inicio de sesión, e instrucciones detalladas para el aviso de Gatekeeper en macOS

### Fixed

- El script de arranque de Windows (`start.bat`) no cargaba los datos de demostración aunque se definiera `LOAD_FIXTURES=true`: le faltaba el paso que ejecuta las fixtures, presente en `start.sh` y `start.ps1`

## [2.0.0] - 2026-06-14

### Added

- Manual de usuario completo redactado en Markdown (`docs/manual/`) como fuente única de verdad: instalación y requisitos, primeros pasos, roles y permisos, flujo de trabajo, referencia de cada sección de la aplicación, notificaciones por email, ajustes, comandos de consola, despliegue y operación
- Generación automática de la documentación a partir de los mismos ficheros Markdown, en dos formatos: PDF (pandoc + pagedjs-cli) y web navegable con buscador (MkDocs Material), mediante los objetivos `make docs-pdf`, `make docs-web`, `make docs-serve` y `make docs`
- Publicación automática de la web del manual en GitHub Pages (<https://reasol-edu.github.io/nexo-fp/>), restringida a la última versión estable: los tags de prelanzamiento y los re-etiquetados de versiones anteriores no despliegan
- Presentación de introducción a Nexo FP para profesorado, escrita en Marp (`docs/slides/`) y exportable a PDF con `make slides`
- Capturas de pantalla del entorno de demostración en la referencia de secciones del manual
- La documentación (PDF del manual, PDF de la presentación y ZIP de la web navegable) se construye en CI y se adjunta a cada GitHub Release con el número de versión en el nombre
- Cada empresa puede registrar un representante (nombre, apellidos y DNI) y su cargo (por ejemplo, «Administrador»); todos los campos son opcionales. Sus datos se indican tanto al crear como al editar la empresa y aparecen también en la exportación CSV de empresas

### Changed

- Reordenadas las tarjetas de la sección «Centro educativo»: Docentes, Oferta formativa, Estudiantes y Ajustes
- Los tutores/as y docentes de grupo ven el detalle de las estancias de sus enseñanzas y los puestos formativos de sus estudiantes, pero ya no ven los puestos formativos sin asignar (puestos libres): ese bloque queda reservado a quienes gestionan la estancia (administración, coordinación o jefatura de departamento) y a los docentes de enlace de las empresas implicadas, tanto en el detalle como en el informe PDF y en la exportación CSV
- Rediseñado el informe PDF de la estancia: el contenido se muestra más compacto para que quepan más datos por página (manteniendo el tamaño del encabezamiento y del pie), y la columna «Empresa / Centro de trabajo» incluye ahora el CIF y el representante (nombre, DNI y cargo) y la de «Tutor/a dual de empresa» muestra el DNI del tutor
- Unificada la terminología de la interfaz con la de la sección «Estancias»: el panel de inicio y las notificaciones usan ahora «puesto/puestos» (en lugar de «plaza/plazas»), «estudiantes» (en lugar de «alumnos») y los estados «Pendiente de Séneca» y «Registrado en Séneca» (en lugar de «Pendiente» y «Completada»); las notificaciones hablan de «tutor dual docente» y «tutor dual de empresa»
- Trasladados a los ficheros de traducción los textos que estaban fijados en plantillas y comandos (tooltips de las gráficas del panel y del listado de estancias, pie de página del informe PDF y descripción del comando `app:setup`), de modo que toda la interfaz sea traducible
- Rediseñado el panel de inicio: la gráfica «Estado de los puestos» pasa a tener cuatro categorías —Borrador, Pendiente de Séneca, Registrado en Séneca (registrado pero sin firmar) y Firmado (registrado y firmado)—, con una paleta de colores coherente con el resto de la aplicación. Se ha sustituido la estadística de firmas por mes por dos gráficas de barras por familia profesional, visibles solo para administradores globales y de centro: «Estudiantes por familia profesional» (clasificados por estado del puesto: sin asignar, borrador, pendiente, registrado y firmado) y «Puestos por familia profesional» (total, ocupados y firmados)
- La configuración del despliegue con Docker pasa a `.env.local` (ignorado por Git) en lugar del `.env` versionado, siguiendo la convención de Symfony: ahora se copia `.env.example` a `.env.local` y se indica a Docker Compose que lo lea con `COMPOSE_ENV_FILES=.env.local` (o `--env-file .env.local`). Así los secretos no se versionan y el `.env` del proyecto queda intacto

### Fixed

- El despliegue con Docker Compose (`docker compose up -d`) solo arrancaba el contenedor de base de datos: el overlay de desarrollo se cargaba automáticamente también en producción (al llamarse `compose.override.yaml`) y dejaba el servicio de aplicación tras el perfil `production`. Ahora ese overlay es `compose.dev.yaml` y se combina explícitamente con `-f` solo en desarrollo, de modo que en producción `docker compose up -d` levanta la aplicación y la base de datos, y deja de exponer el puerto de PostgreSQL en el host
- El mensaje de error al marcar un puesto como firmado citaba un estado inexistente («Completado»); ahora indica el estado correcto («Registrado en Séneca»)
- Al desvincular un empleado de una empresa se mostraba siempre el mensaje de éxito aunque el empleado no estuviera vinculado a esa empresa; ahora solo se confirma cuando realmente se desvincula y, en caso contrario, se avisa con un mensaje de error
- Corregidas erratas ortográficas y etiquetas inconsistentes en la interfaz: «sobreescribir» → «sobrescribir», «Correo-e profesional» → «Email profesional», «Alumnos» → «Estudiantes» y un saludo de bienvenida en forma inclusiva
- Al desasignar un puesto formativo ahora se borran también el tutor/a dual docente y el tutor/a dual de empresa antes de devolverlo a la lista de puestos sin asignar
- En el detalle de la estancia no aparecía el desplegable para elegir tutor/a dual de empresa en los puestos en borrador con estudiante asignado y sin mentor; la consulta que cargaba los empleados por empresa no resolvía los identificadores UUID y devolvía siempre una lista vacía
- Con Docker, establecer `LOAD_FIXTURES=true` en el `.env` no cargaba los datos de demostración porque la variable no se reenviaba al contenedor; ahora se declara en el bloque `environment:` del servicio `app` de `compose.yaml`
- En la pantalla de inicio de sesión, el tabulador desde el campo de usuario salta ahora directamente a la contraseña: el enlace «¿Olvidaste tu contraseña?» se ha movido en el orden de tabulación a continuación del campo de contraseña sin perder su accesibilidad (sigue siendo enlazable y enfocable por teclado)

## [1.6.1] - 2026-06-13

### Changed

- Las transiciones de estado de los puestos formativos (borrador → pendiente de Séneca → registrado) se gestionan ahora con una máquina de estados (Symfony Workflow), que centraliza las transiciones permitidas y la guarda de que un puesto solo puede salir de borrador cuando tiene asignados el tutor dual docente y el tutor dual de empresa

## [1.6.0] - 2026-06-13

### Security

- El aviso de cambio de correo pendiente en el perfil escapaba el correo introducido por el usuario con `|raw`, lo que permitía inyectar HTML/JavaScript a través de un correo con partes locales entrecomilladas (`"<script>"@dominio`) aceptadas por `FILTER_VALIDATE_EMAIL`; ahora el correo se escapa antes de insertarse y solo el marcado fijo del mensaje se renderiza sin escapar
- Protección contra fuerza bruta en el inicio de sesión (`login_throttling`): máximo 5 intentos fallidos por usuario e IP cada 15 minutos
- Límite de peticiones en la solicitud de recuperación de contraseña (5 cada 15 minutos por IP) para evitar el bombardeo de correos y la enumeración de usuarios por tiempo de respuesta
- Límite de peticiones en la búsqueda global ⌘K (60 por minuto y usuario)
- Cookie de sesión endurecida con `cookie_secure: auto`, `cookie_httponly: true` y `cookie_samesite: lax`
- El controlador de búsqueda exige explícitamente el rol `ROLE_TEACHER` (defensa en profundidad)

### Added

- Envío de correos en segundo plano mediante Messenger: la verificación de cambio de correo y las notificaciones de tutoría/firma se encolan en un transporte asíncrono (`doctrine://`) con 3 reintentos; la recuperación de contraseña sigue siendo síncrona por ser urgente (token de 1 hora). Los ejecutables binarios lanzan el consumidor (`messenger:consume`) desde sus scripts de arranque y lo detienen al finalizar
- Nueva migración de la tabla `messenger_messages` (SQLite, PostgreSQL y MySQL) creada con `auto_setup=0` para evitar DDL durante una petición
- Protección frente al doble envío de formularios: un controlador Stimulus deshabilita el botón al enviar (creación/edición de estancias, puestos y gestión de estudiantes)
- En instalaciones SQLite se activan los PRAGMA `journal_mode=WAL` y `busy_timeout` para reducir la contención de escritura entre el servidor web y el consumidor de la cola

### Changed

- La desasignación de un puesto formativo pide confirmación mediante el mismo patrón de modal que el resto de acciones destructivas
- El listado de estancias muestra un botón «Limpiar filtros» cuando una búsqueda no devuelve resultados
- Los meses del calendario de estancias dejan de estar codificados en español y se obtienen de las traducciones
- Mejoras de accesibilidad: `aria-label` en botones de solo icono, `aria-current="page"` en la navegación activa, migas de pan completas en las páginas de creación e indicador de carga al navegar entre meses del calendario
- El campo de enseñanza del formulario de estancia indica que es obligatorio, el cuadro de búsqueda ⌘K limita la entrada a 100 caracteres, los nombres largos se truncan en tarjetas y detalle, y los formularios muestran un resumen de errores de validación
- Los trabajadores de las empresas se precargan en una sola consulta en el detalle de estancia, eliminando una consulta por puesto (N+1)

### Fixed

- El detalle de una estancia inexistente devolvía un error 500 (`RuntimeException`) en lugar de una página 404
- Al solicitar una página posterior a la última del listado de estancias se mostraba una lista vacía; ahora se ajusta automáticamente a la última página válida

## [1.5.2] - 2026-06-12

### Fixed

- Las gráficas de rosco del listado de estancias se mostraban vacías cuando un segmento cubría el 100% del círculo: al aplicar `stroke-dasharray` con la longitud del arco igual a la circunferencia del SVG y `stroke-dashoffset` igual a esa misma circunferencia, el camino completo caía en la zona de hueco del patrón y no se pintaba nada; cuando un segmento es el único presente (100%), se sustituye el truco de `stroke-dasharray` por un círculo sólido sin patrón de trazos

## [1.5.1] - 2026-06-12

### Changed

- Las tarjetas de estancia muestran dos gráficas de rosco SVG en lugar de las cuatro filas de texto: la primera representa el estado de inscripción de estudiantes (con y sin puesto) y la segunda el estado de los puestos formativos (sin asignar, borrador/pendiente, registrado y firmado); en el hueco central se mantienen los indicadores numéricos de siempre
- Las barras del gráfico de plazas por familia profesional en el panel incluyen ahora el número de plazas dentro de cada segmento, y las barras de cada fila se unen con esquinas rectas en lugar de redondeadas

### Fixed

- El desplegable de asignación de puesto formativo a estudiante no se abría al hacer clic: el script del componente buscaba el elemento raíz con `querySelector('[data-controller~="live"]')`, que devolvía el componente `NotificationBellComponent` (renderizado antes en la cabecera) en lugar del propio `StayDetailComponent`; la delegación de eventos y la inicialización de TomSelect se ejecutaban sobre el elemento incorrecto
- Al asignar un puesto formativo a un estudiante aparecía por defecto un tutor dual docente: los datos de demostración creaban puestos en estado borrador y sin alumno ya con `academicTutor` asignado, lo que no refleja el flujo real; esos puestos sin estudiante ya no tienen tutor predefinido

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
