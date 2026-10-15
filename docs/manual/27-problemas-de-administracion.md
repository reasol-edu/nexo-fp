# Problemas de administración

Soluciones a las dudas más habituales al preparar y administrar el centro desde la aplicación. Si el
problema está en el servidor (no arranca, no hay correo, no se sincroniza), consulta
[Resolución de problemas](36-resolucion-de-problemas.md).

## Un docente no ve ninguna estancia, empresa o sección

El acceso depende de los roles, que **se derivan de los datos** del centro (equipo directivo, jefatura de
familia, coordinación de FP dual, tutoría de grupo, docente de enlace…). Un docente sin ninguna de esas
asignaciones solo ve el panel de inicio y su perfil. Revisa sus asignaciones en **Centro Educativo** y
consulta [Roles y permisos](21-roles-y-permisos.md).

## Problemas con la importación de estudiantes

### El CSV no crea estudiantes o faltan grupos

- El fichero debe tener la **primera fila de encabezados** con los nombres de columna exactos y solo se
  importan las filas con `Estado Matrícula` **vacío**. Revisa el
  [formato del CSV](22-primeros-pasos.md#formato-del-csv-de-importacion).
- Si aparecen «unidades no reconocidas», los grupos del curso no se llaman igual que la columna `Unidad`
  de Séneca. Renombra los grupos para que coincidan exactamente (ver
  [Primeros pasos, paso 3](22-primeros-pasos.md#3-estructurar-la-oferta-formativa-del-curso-academico-equipo-directivo)).

## Un docente no recibe los correos

1. Comprueba que tiene una **dirección de correo** (la lista de [Revisión de datos](23-centro-y-cursos.md#centro-educativo)
   del centro muestra los docentes sin correo).
2. Comprueba en [Ajustes y bloqueos](25-ajustes-y-bloqueos.md) que el aviso está habilitado a nivel global,
   de centro y personal, y si tiene activado el resumen diario (entonces los avisos llegan una vez al día).
3. Consulta el [registro de correos](26-control-y-registro-de-correos.md#registro-de-correos-enviados): si
   aparece como **Fallido**, el problema es del servidor de correo y lo debe revisar quien administra el
   servidor (consulta [Resolución de problemas](36-resolucion-de-problemas.md#no-llegan-los-emails)).

## Un ajuste aparece deshabilitado

Está **bloqueado** por un nivel superior (el centro o la administración global). Aparece el valor que se
aplica y quién lo ha fijado; solo ese nivel puede cambiarlo o desbloquearlo. Consulta
[Ajustes y bloqueos](25-ajustes-y-bloqueos.md#bloqueo-de-ajustes).

## He olvidado la contraseña de administrador

No se puede recuperar la contraseña del usuario `admin` original, pero sí dar de alta otro administrador
desde la línea de comandos, algo que hace quien administra el servidor: consulta
[Resolución de problemas](36-resolucion-de-problemas.md#he-olvidado-la-contrasena-de-administrador).
