# Primeros pasos

## 1. Crear o activar el curso académico (administrador)

Antes de poder gestionar estancias, el **administrador de centro** debe preparar el curso académico
activo para cada centro educativo. Este capítulo describe la configuración inicial; el recorrido completo para dejar 
listo para su uso un curso académico está detallado en el capítulo [Flujo de trabajo](04-flujo-de-trabajo.md).

Todo en Nexo FP gira en torno al **curso académico activo** de cada centro: al cambiar de curso, cambia el
contexto de trabajo. Desde **Centro Educativo** se gestionan el contenido del curso académico activo.

## 2. Añadir a los docentes del curso académico (equipo directivo)

Inicialmente, se recomienda añadir el **personal adscrito al curso** para que pueda acceder a la plataforma
con sus respectivos roles.

## 2. Estructurar la oferta formativa del curso académico (equipo directivo)

La oferta formativa es una estructura jerárquica que se construye de arriba a abajo:

```
Familia profesional → Enseñanza (ciclo) → Nivel (curso) → Grupo
```

- **Familias profesionales**: cada una con su jefe/a de departamento.
- **Enseñanzas**: los ciclos formativos (CFGM, CFGS…) de cada familia.
- **Niveles**: los cursos dentro de la enseñanza (1.º, 2.º…).
- **Grupos**: dentro de cada nivel, con asignación de **tutor/a** y **docentes**. 
  Para evitar problemas de importación, los nombres de los grupos tienen que ser
  exactamente iguales que los que aparecen en Séneca (por ejemplo, `1º DAW A` o `1DAWA`, `1º DAW-A`, etc.)

> Los roles de coordinación de FP dual, jefatura de familia profesional y tutoría se asignan a
> **personas concretas**: de esas asignaciones se derivan los permisos de cada docente
> (consulta [Roles y permisos](03-roles-y-permisos.md)).

## 3. Asignar tutores y docentes a los grupos (equipo directivo)

Para cada grupo se designan el tutor/a del mismo (puede seleccionarse más de uno) y los docentes
que imparten clase en él. Estas asignaciones determinan qué estancias podrá ver cada docente.

## 4. Dar de alta a los estudiantes (equipo directivo)

Desde **Centro Educativo → Estudiantes** se dan de alta los estudiantes, manualmente o mediante
**importación masiva por CSV desde Séneca**, y se distribuyen en sus grupos. El listado permite 
búsqueda por NIE o nombre, filtro por grupo y **exportación a CSV**.

Con el curso configurado, el centro está listo para registrar empresas y crear estancias.
