# Ajustes

La aplicación dispone de un sistema de configuración con tres niveles de granularidad:

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
| `email.notification.signature_reminder` | Booleano | Global, centro, personal | Recordatorio de firma |
| `email.notification.signature_reminder.days` | Entero (1–365) | Global, centro | Días de antelación con los que se empieza a avisar de la firma pendiente (por defecto 7) |
| `email.log_retention_days` | Entero (0–3650) | Global | Días que se conserva cada entrada del [registro de correos enviados](06-notificaciones-y-email.md#registro-de-correos-enviados) antes de que la limpieza semanal la elimine. `0` desactiva la limpieza (por defecto 90) |
| `security.idle_timeout_minutes` | Entero (0–1440) | Global | Minutos sin actividad tras los que se cierra la sesión de un docente, para que un equipo compartido que se deja abierto no quede utilizable por otra persona. `0` lo desactiva (por defecto 120). La pantalla de inicio de sesión explica por qué se ha cerrado |
