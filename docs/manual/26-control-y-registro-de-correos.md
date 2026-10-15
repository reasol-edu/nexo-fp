# Control y registro de correos

Este capítulo explica cómo **controlar los avisos por correo** que envía Nexo FP desde la propia aplicación
y cómo **comprobar qué correos se han enviado**. Los avisos que recibe cada persona se describen en
[Avisos por correo](14-avisos-por-correo.md); cómo se conecta el servidor de correo (SMTP, Gmail, cola de
envío) lo hace quien despliega la aplicación, en [Configuración del correo](33-configuracion-del-correo.md).

## Control por niveles

Cada tipo de aviso puede habilitarse o deshabilitarse individualmente desde la sección **Ajustes**, con
tres niveles de granularidad: **global**, por **centro educativo** y por **docente**. El valor más
específico tiene prioridad, salvo que un nivel superior lo haya **bloqueado**. Si un docente desactiva las
notificaciones desde su perfil, no recibirá correos independientemente de la configuración global o del
centro, a menos que esa configuración esté bloqueada. Los detalles, y la lista completa de ajustes, están
en [Ajustes y bloqueos](25-ajustes-y-bloqueos.md).

Algunas decisiones habituales:

- **Reducir el número de correos.** El ajuste «Resumen diario de avisos por correo» agrupa en un solo
  correo diario por centro la tutoría asignada, los puestos creados, los cambios en puestos compartidos y
  el recordatorio de firma. Cada docente lo activa en sus ajustes; la administración global puede
  activarlo para todo el mundo (y bloquearlo) desde el nivel global. «Hora del resumen diario» fija a qué
  hora llega (7:00 por defecto).
- **Anticipar el recordatorio de firma.** Los días de antelación con los que empieza a avisarse de una
  firma pendiente se fijan por centro (7 por defecto).
- **Apagar los correos de un centro o de toda la plataforma.** El interruptor general «Notificaciones por
  email» desactiva todos los avisos del nivel en el que se fije.

## Registro de correos enviados {#registro-de-correos-enviados}

La aplicación deja constancia de **cada correo que intenta enviar**: avisos de tutoría asignada, de puestos
creados, recordatorios de firma, resúmenes diarios y semanales, además de los correos de restablecimiento
de contraseña y de verificación de una dirección nueva. Sirve para responder a «¿por qué no me ha llegado
el aviso?» sin tener que revisar los logs del servidor.

- **Administración del centro:** en **Centro educativo** aparece la tarjeta **Registro de correos**, con los
  correos enviados a docentes de ese centro.
- **Administración global:** la tarjeta **Registro de correos** del panel de Administración muestra los de
  todos los centros, incluidos los que no pertenecen a ninguno (restablecer contraseña, verificar correo).

Cada entrada indica la fecha y la hora, el destinatario (nombre y dirección), el tipo de aviso, el asunto
y el resultado. Se puede buscar por destinatario, dirección o asunto, y filtrar por tipo de aviso,
resultado (**Enviado** / **Fallido**, con el motivo del fallo) y rango de fechas.

!!! info "Qué significa «Enviado»"
    Significa que el servidor de correo configurado **aceptó el mensaje**. Con el envío asíncrono (ver
    [Envío asíncrono](33-configuracion-del-correo.md#envio-asincrono)) el correo pasa antes por la cola y
    el resultado final de la entrega al buzón del destinatario no se conoce desde la aplicación.

El registro **no guarda el contenido** de los mensajes (los de restablecimiento y verificación llevan
enlaces con un token), solo los datos anteriores. Las entradas se conservan **90 días** por defecto; el
ajuste global «Conservación del registro de correos» (`email.log_retention_days`, ver
[Ajustes y bloqueos](25-ajustes-y-bloqueos.md)) lo cambia, y `0` desactiva la limpieza semanal automática.
