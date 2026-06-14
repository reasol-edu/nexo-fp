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
| `email.notification.signature_reminder` | Booleano | Global, centro, personal | Recordatorio de firma |
