# Ajustes y bloqueos

La aplicación dispone de un sistema de configuración con tres niveles de granularidad. Cada docente
cambia sus preferencias en [Ajustes personales](15-ajustes-personales.md); este capítulo explica los tres
niveles y cómo la administración puede fijar valores para todo el centro o toda la plataforma.

| Nivel | URL | Quién puede acceder |
|-------|-----|---------------------|
| Global | `/admin/ajustes` | Administradores globales |
| Centro educativo | `/mi-centro/ajustes` | Administradores de centro |
| Personal | `/perfil/ajustes` | Todos los docentes autenticados |

Los valores se resuelven en cascada: **personal > centro > global > predeterminado**.

## Bloqueo de ajustes

Los administradores globales y de centro pueden **bloquear** cualquier ajuste que tengan explícitamente
guardado. Un ajuste bloqueado a nivel global no puede ser modificado por los centros ni por los docentes;
uno bloqueado a nivel de centro no puede ser modificado por los docentes de ese centro. Los ajustes
bloqueados aparecen deshabilitados en los niveles inferiores, indicando qué nivel los ha fijado, y el
control muestra siempre el valor fijado por el nivel bloqueante. Un ajuste bloqueado tampoco puede
restablecerse al valor por defecto.

## Ajustes disponibles

| Clave | Tipo | Ámbito | Descripción |
|-------|------|--------|-------------|
| `page.size` | Entero (5–100) | Personal | Elementos por página en los listados |
| `email.notifications` | Booleano | Global, centro, personal | Interruptor maestro de notificaciones |
| `email.notification.tutor_assigned` | Booleano | Global, centro, personal | Aviso al asignar una tutoría |
| `email.notification.positions_created` | Booleano | Global, centro, personal | Aviso al crear puestos formativos |
| `email.notification.shared_position` | Booleano | Global, centro, personal | Aviso a la coordinación cuando otra asigna o elimina un puesto compartido de la estancia |
| `email.notification.weekly_digest` | Booleano | Global, centro, personal | Resumen semanal (lunes) de tareas pendientes, con enlaces a cada estancia |
| `email.notification.signature_reminder` | Booleano | Global, centro, personal | Recordatorio de firma |
| `email.digest` | Booleano | Global, personal | Recibir los avisos (tutoría asignada, puestos creados, puestos compartidos, recordatorio de firma) en un único [resumen diario](14-avisos-por-correo.md#un-resumen-diario-en-lugar-de-muchos-correos) en vez de un correo por aviso (por defecto desactivado) |
| `email.digest_hour` | Entero (0–23) | Global, personal | Hora del día (del servidor) a la que se envía el resumen diario (por defecto 7) |
| `email.notification.signature_reminder.days` | Entero (1–365) | Global, centro | Días de antelación con los que se empieza a avisar de la firma pendiente (por defecto 7) |
| `attention.urgent_days` | Entero (1–60) | Global, centro | Días hasta el inicio de una estancia por debajo de los cuales las tareas del inicio son *críticas* (por defecto 7) |
| `attention.soon_days` | Entero (2–180) | Global, centro | Días hasta el inicio por debajo de los cuales las tareas son *próximas* (por defecto 21). Siempre se aplica un valor mayor que el de `attention.urgent_days` |
| `email.log_retention_days` | Entero (0–3650) | Global | Días que se conserva cada entrada del [registro de correos enviados](26-control-y-registro-de-correos.md#registro-de-correos-enviados) antes de que la limpieza semanal la elimine. `0` desactiva la limpieza (por defecto 90) |
| `security.idle_timeout_minutes` | Entero (0–1440) | Global | Minutos sin actividad tras los que se cierra la sesión de un docente, para que un equipo compartido que se deja abierto no quede utilizable por otra persona. `0` lo desactiva (por defecto 120). La pantalla de inicio de sesión explica por qué se ha cerrado |
| `maintenance.enabled` | Booleano | Global | **Modo mantenimiento** (por defecto desactivado). Con él activado solo los administradores globales pueden usar la aplicación, también si usan «Acceder como»: el resto del profesorado ve una página de mantenimiento (código 503) y la pantalla de inicio de sesión lo avisa. Los administradores ven una franja que les recuerda que está activado. Los avisos por correo y las demás tareas automáticas siguen funcionando |

## Modo mantenimiento

Actívalo en **Administración → Ajustes globales** antes de tareas delicadas, como actualizar la aplicación o
restaurar una copia de seguridad: el profesorado deja de poder entrar y no trabaja sobre datos que van a
cambiar. Solo siguen abiertos el acceso, la salida y los recursos estáticos; todo lo demás, incluido el
restablecimiento de contraseña, devuelve la página de mantenimiento. Al terminar, desactiva el ajuste desde
el enlace de la franja que ve la administración.
