# Roles y permisos

Todos los usuarios del sistema son docentes. El nivel de acceso depende de los roles y
responsabilidades asignados, que son **acumulativos**: un docente con varios roles acumula todos sus
permisos.

## Acceso a la plataforma

El inicio de sesión es siempre el **mismo formulario** (usuario y contraseña). Lo que cambia es **cómo se
valida la contraseña**, según el **tipo de autenticación** que el administrador asigna a cada docente
desde **Administración → Docentes**:

- **Acceso local.** La contraseña la gestiona Nexo FP (se guarda cifrada). Estos docentes pueden usar el
  enlace **«¿Olvidaste tu contraseña?»** para restablecerla por email (consulta
  [Notificaciones por email](06-notificaciones-y-email.md)). La contraseña debe tener **al menos 12
  caracteres**; tanto el formulario de perfil como el de restablecimiento la rechazan si es más corta.
- **Acceso externo (Séneca/iSéneca).** El docente entra con su **usuario y contraseña de Séneca**, que se
  validan contra el servicio **iSéneca** de la Junta de Andalucía. La contraseña no se guarda en Nexo FP,
  así que el restablecimiento por email **no aplica**: se gestiona desde Séneca.

!!! info "Configuración"
    La autenticación externa se habilita en el despliegue con `APP_EXTERNAL_ENABLED` y las variables
    `APP_EXTERNAL_*` (consulta [Despliegue](09-despliegue.md#variables-de-entorno-opcionales)). Si está
    desactivada, todos los accesos son locales.

## Los perfiles

### Administrador global

Acceso completo a la aplicación, incluida la sección **Administración**. Puede gestionar todos los
docentes y centros del sistema, y suplantar la identidad de cualquier usuario para solucionar problemas.

Se crea al menos uno durante el primer arranque (`admin` / `admin`). Se pueden crear más desde la línea
de comandos con [`app:create-admin`](08-comandos-de-consola.md#appcreate-admin) o desde la sección de 
"Administración".

### Administrador de centro 

Docente designado como responsable de un centro educativo concreto. Normalmente se corresponderá con personas 
del equipo directivo. Tiene acceso completo a ese centro: oferta formativa, alumnado, docentes del curso, empresas y 
estancias. No tiene acceso a la sección de administración global.

### Coordinador/a de FP dual

Docente asignado como coordinador/a de una o varias enseñanzas. Tiene acceso a la sección **Empresas**
(ver y editar todas las empresas del centro) y puede crear, modificar y eliminar estancias de las
enseñanzas que coordina, así como gestionar sus puestos formativos y las asignaciones de estudiantes y
tutores. Al crear una nueva estancia, solo puede seleccionar enseñanzas de las que es coordinador/a.

### Jefe/a de departamento de familia profesional

Docente designado/a como jefe/a de departamento de una familia profesional. Tiene acceso a la sección
**Empresas** (ver y editar cualquier empresa del centro) y puede ver, editar y eliminar las estancias de
las enseñanzas de su familia profesional, así como gestionar sus puestos formativos.

### Docente de enlace

Docente asignado/a a una o varias empresas del centro. Puede acceder a la sección **Empresas** y editar
los datos de aquellas empresas de las que es enlace: centros de trabajo, empleados y docentes de enlace.
Su acceso a la sección **Estancias** está limitado a las estancias con puestos formativos en sus
empresas, y solo puede editar o eliminar los puestos **sin estudiante asignado**.

### Tutor/a de grupo / Docente de grupo

Docente asignado a un grupo como tutor o docente. Puede **ver** las estancias de la enseñanza
correspondiente y consultar los puestos formativos **con estudiante asignado**, pero no puede modificar
las estancias ni ver los puestos formativos **sin estudiante asignado**.

### Docente (sin rol específico)

Rol base de todos los usuarios autenticados. Accede al panel de inicio y a su propio perfil. Un docente
sin ningún rol específico en el centro no tiene acceso a estancias, empresas ni al área de Centro
Educativo.

## Tabla de permisos

Las celdas con ✅ indican acceso completo; ❌, sin acceso. Cuando el acceso es parcial se indica el
ámbito: **«Su familia prof.»** = estancias o enseñanzas de su familia profesional; **«Sus enseñanzas»**
= las que coordina; **«Sus empresas»** = las que tiene asignadas como enlace.

| Abrev. | Rol |
|--------|-----|
| **ADM** | Administrador/a global |
| **ED** | Administrador/a de centro |
| **JFP** | Jefe/a de departamento de familia profesional |
| **CFD** | Coordinador/a de FP dual |
| **DE** | Docente de enlace |
| **TG** | Tutor/a de grupo / Docente de grupo |
| **D** | Docente (sin rol específico en el centro) |

### Centro educativo

| Acción | ADM | ED | JFP | CFD | DE | TG | D |
|--------|:---:|:--:|:---:|:---:|:--:|:--:|:-:|
| Acceder a la sección | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestionar docentes del curso | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestionar estudiantes | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestionar oferta formativa¹ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Crear y activar cursos académicos | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

¹ Familias profesionales, enseñanzas, niveles y grupos.

### Estancias

| Acción | ADM | ED | JFP | CFD | DE | TG | D |
|--------|:---:|:--:|:---:|:---:|:--:|:--:|:-:|
| Ver estancias | ✅ | ✅ | Su familia prof. | Sus enseñanzas | Sus empresas³ | Sus enseñanzas | ❌ |
| Ver puestos sin asignar | ✅ | ✅ | Su familia prof. | Sus enseñanzas | Sus empresas³ | ❌ | ❌ |
| Crear estancia | ✅ | ✅ | Su familia prof. | Sus enseñanzas | ❌ | ❌ | ❌ |
| Editar / eliminar estancia | ✅ | ✅ | Su familia prof. | Sus enseñanzas | ❌ | ❌ | ❌ |
| Añadir puestos formativos | ✅ | ✅ | Su familia prof. | Sus enseñanzas | Sus empresas³ | ❌ | ❌ |
| Editar / eliminar puestos formativos | ✅ | ✅ | Su familia prof. | Sus enseñanzas | Sus empresas³⁴ | ❌ | ❌ |
| Inscribir / retirar estudiantes | ✅ | ✅ | Su familia prof. | Sus enseñanzas | ❌ | ❌ | ❌ |
| Descargar informe PDF / exportar a Excel | ✅ | ✅ | Su familia prof. | Sus enseñanzas | Sus empresas³ | Sus enseñanzas | ❌ |

### Empresas

| Acción | ADM | ED | JFP | CFD | DE | TG | D |
|--------|:---:|:--:|:---:|:---:|:--:|:--:|:-:|
| Acceder a la sección | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Ver y buscar empresas | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Crear empresa | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Editar empresa² | ✅ | ✅ | ✅ | ✅ | Sus empresas | ❌ | ❌ |
| Eliminar empresa | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Administración global

| Acción | ADM | ED | JFP | CFD | DE | TG | D |
|--------|:---:|:--:|:---:|:---:|:--:|:--:|:-:|
| Acceder a la sección | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestionar docentes del sistema | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestionar centros educativos | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### Otras acciones y permisos generales

| Acción | ADM | ED | JFP | CFD | DE | TG | D |
|--------|:---:|:--:|:---:|:---:|:--:|:--:|:-:|
| Acceder a la plataforma | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ver panel de inicio | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Gestionar el propio perfil | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Acceder como otro usuario (suplantación) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

**Notas:**

² Incluye centros de trabajo, empleados y docentes de enlace asignados.
³ Solo estancias/puestos donde intervienen sus empresas asignadas.
⁴ Solo puestos sin estudiante asignado. Los puestos con estudiante asignado no pueden ser modificados ni
eliminados por el docente de enlace.
