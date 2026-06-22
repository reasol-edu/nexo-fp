# Primeros pasos

## 1. Crear o activar el curso académico (administrador)

Antes de poder gestionar estancias, el **administrador de centro** debe preparar el curso académico
activo para cada centro educativo. Este capítulo describe la configuración inicial; el recorrido completo
para dejar listo para su uso un curso académico está detallado en el capítulo
[Flujo de trabajo](04-flujo-de-trabajo.md).

Todo en Nexo FP gira en torno al **curso académico activo** de cada centro: al cambiar de curso, cambia
el contexto de trabajo. Desde **Centro Educativo** se gestiona todo su contenido.

!!! danger "Cambia la contraseña por defecto antes de empezar"
    El primer acceso se hace con `admin` / `admin`. Entra y, en **Mi perfil → Contraseña**, establece
    una contraseña robusta de **al menos 12 caracteres**. No dejes nunca las credenciales por defecto
    en una instalación accesible por red.

## 2. Añadir los docentes del curso académico (equipo directivo)

Desde **Centro Educativo → Docentes del curso** se da de alta al personal adscrito al curso activo.
**Este paso es imprescindible antes de estructurar la oferta formativa**: sin personal dado de alta en el
curso no es posible asignar jefaturas de departamento, coordinadores duales, tutores ni docentes a los grupos.

Los docentes pueden añadirse uno a uno o mediante **importación masiva desde CSV de Séneca**.

### Cómo exportar el CSV de Séneca (perfil Dirección)

En Séneca, con el perfil **Dirección**:

**Personal → Personal del centro → Exportar datos** (seleccionar formato **CSV**)

El fichero resultante contiene el `Usuario IdEA` y el nombre del personal del centro. Si el docente
ya existe en el sistema, se añade al curso activo sin modificar sus datos. Si no existe, se crea
automáticamente con autenticación externa (IdEA).

## 3. Estructurar la oferta formativa del curso académico (equipo directivo)

La oferta formativa es una estructura jerárquica que se construye de arriba a abajo:

> Familia profesional → Enseñanza (ciclo) → Nivel (curso) → Grupo

- **Familias profesionales**: cada una con su jefe/a de departamento.
- **Enseñanzas**: los ciclos formativos (CFGM, CFGS…) de cada familia.
- **Niveles**: los cursos dentro de la enseñanza (1.º, 2.º…).
- **Grupos**: dentro de cada nivel, con asignación de **tutor/a** y **docentes**. 
  Para evitar problemas de importación, los nombres de los grupos tienen que ser
  exactamente iguales que los que aparecen en Séneca (por ejemplo, `1º DAW A` o `1DAWA`, `1º DAW-A`, etc.)

### Cómo registrarla, paso a paso

Entra en **Centro Educativo → Oferta formativa**. Toda la oferta se gestiona en **una sola pantalla**
organizada en columnas, de izquierda a derecha: **Familias**, **Enseñanzas**, **Niveles** y **Grupos**.
No hace falta cambiar de página ni guardar entre paso y paso: cada columna se va abriendo al pulsar sobre
un elemento de la columna anterior.

1. **Crea una familia profesional.** En la primera columna, escribe el nombre en la casilla inferior
   («Añadir familia…») y pulsa el botón **+**. La familia aparece en la lista y queda seleccionada.
2. **Añade sus enseñanzas.** Con la familia seleccionada, la segunda columna queda activa: escribe el
   nombre del ciclo y pulsa **+**. Repite para cada enseñanza de esa familia.
3. **Añade los niveles.** Selecciona una enseñanza y, en la tercera columna, añade sus cursos
   (1.º, 2.º…) de la misma forma.
4. **Añade los grupos.** Selecciona un nivel y, en la cuarta columna, añade cada grupo escribiendo su
   nombre **tal cual aparece en Séneca**.
5. **Completa los datos de cada elemento.** Al seleccionar cualquier familia, enseñanza, nivel o grupo,
   a la derecha se abre un panel donde puedes cambiar su nombre, escribir observaciones y asignar al
   personal correspondiente (responsable de familia, coordinadores/as, tutores/as y docentes). Para
   asignar personal, pulsa el desplegable y elige de la lista o escribe unas letras para filtrar; las
   asignaciones de personal **se guardan solas**, y el nombre y las observaciones se confirman con
   **Guardar cambios**.

Para borrar un elemento, selecciónalo y usa el botón de eliminar del panel de la derecha; te pedirá
confirmación. Al borrar una familia, enseñanza o nivel se eliminan también los elementos que cuelgan de
él.

!!! tip "¿Ya tienes la oferta en otro curso o centro?"
    No hace falta teclearla de nuevo: desde la página de **Oferta formativa** puedes **exportarla a un
    fichero** e **importarla** en otro curso o centro. Consulta
    [Exportar e importar la oferta formativa](05-secciones-de-la-aplicacion.md#exportar-e-importar-la-oferta-formativa).

!!! info
    Los roles de coordinación de FP dual, jefatura de familia profesional y tutoría se asignan a
    **personas concretas**: de esas asignaciones se derivan los permisos de cada docente
    (consulta [Roles y permisos](03-roles-y-permisos.md)).

## 4. Asignar tutores y docentes a los grupos (equipo directivo)

Para cada grupo se designan el tutor/a del mismo (puede seleccionarse más de uno) y los docentes
que imparten clase en él. Estas asignaciones determinan qué estancias podrá ver cada docente.

Las asignaciones pueden hacerse manualmente —seleccionando el grupo en la columna **Grupos** y
eligiendo el personal en el panel de la derecha, como se explica en el paso anterior— o mediante
**importación masiva desde CSV de Séneca**.

### Cómo exportar el CSV de asignaciones de Séneca (perfil Dirección)

En Séneca, con el perfil **Dirección**:

**Personal → Personal del centro → Materia y grupos → Unidad: Cualquiera → Exportar datos** (seleccionar formato **CSV**)

El fichero contiene la columna `Unidad` (nombre del grupo) y `Profesor/a` (apellidos y nombre). El
grupo se busca por nombre exacto entre los grupos del curso activo, y el docente por nombre y apellidos
exactos entre los del curso activo — razón por la que el paso 2 (importar docentes) debe completarse
antes.

## 5. Dar de alta a los estudiantes (equipo directivo)

Desde **Centro Educativo → Estudiantes** se dan de alta los estudiantes, manualmente o mediante
**importación masiva por CSV desde Séneca**, y se distribuyen en sus grupos. El listado permite
búsqueda por NIE o nombre, filtro por grupo y **exportación a Excel**.

### Cómo exportar el CSV de Séneca (perfil Dirección)

En Séneca, con el perfil **Dirección**:

**Alumnado → Alumnado del centro → (seleccionar curso/unidad o dejar en blanco para todos) → Exportar datos** (seleccionar formato **CSV**)

### Formato del CSV de importación

La importación está pensada para el fichero que **exporta Séneca** con el alumnado matriculado. La
**primera fila** debe contener los encabezados de columna y el fichero debe incluir, al menos, estas
columnas (los nombres deben coincidir exactamente):

| Columna | Uso en Nexo FP |
|---------|----------------|
| `Estado Matrícula` | Filtra las filas: solo se importan las que tienen este campo **vacío** (matrícula activa). Las filas con cualquier valor (baja, traslado, anulada…) se omiten. |
| `Nº Id. Escolar` | Identificador del estudiante (NIE). **Obligatorio**; es la clave para no duplicar. |
| `Nombre` | Nombre del estudiante. |
| `Primer apellido` | Primer apellido (obligatorio). |
| `Segundo apellido` | Segundo apellido (opcional; se une al primero). |
| `Unidad` | Grupo al que pertenece. Debe coincidir con un grupo del curso activo (sin distinguir mayúsculas). |

Notas sobre el comportamiento:

- Los estudiantes que ya existen (mismo `Nº Id. Escolar`) se **actualizan**; los nuevos se **crean**.
- Si la `Unidad` no coincide con ningún grupo del curso, el estudiante se importa **sin grupo** y al
  final se muestra la lista de unidades no reconocidas. Por eso es importante que los grupos se llamen
  **exactamente** igual que en Séneca (ver el paso 3).
- El fichero puede estar codificado en UTF-8 o Windows-1252 (se detecta automáticamente).

Con el curso configurado, el centro está listo para registrar empresas y crear estancias.
