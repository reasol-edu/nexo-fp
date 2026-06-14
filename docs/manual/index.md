# Introducción

**Nexo FP** es una aplicación web, desarrollada con [Symfony](https://symfony.com/), para organizar y
gestionar la **Fase de Formación en Empresa u Organismo Equiparado** de la Formación Profesional.
Centraliza la información de estudiantes, empresas, puestos formativos y tutores, y permite llevar el
seguimiento del proceso de asignación desde que se crea un puesto hasta que se registra en Séneca.

La aplicación se ha diseñado para ser intuitiva y fácil de usar, con un enfoque en la eficiencia y la
reducción de errores administrativos. Permite generar informes detallados en PDF y facilita la
comunicación entre el centro educativo y las empresas.

## Multicentro

Nexo FP es **multicentro**: un mismo servidor puede alojar varios centros educativos con datos
completamente separados. Cada docente selecciona el centro activo al iniciar sesión y solo ve los datos
de ese centro. Los administradores globales pueden gestionar todos los centros desde la sección
**Administración**.

## Cómo usar este manual

El manual sigue el orden natural de uso de la aplicación:

1. [Instalación y requisitos](01-instalacion-y-requisitos.md) — poner en marcha la aplicación.
2. [Primeros pasos](02-primeros-pasos.md) — preparar el curso académico.
3. [Roles y permisos](03-roles-y-permisos.md) — quién puede hacer qué.
4. [Flujo de trabajo](04-flujo-de-trabajo.md) — el recorrido completo de un curso.
5. [Secciones de la aplicación](05-secciones-de-la-aplicacion.md) — referencia de cada pantalla.
6. [Notificaciones por email](06-notificaciones-y-email.md) — avisos automáticos.
7. [Ajustes](07-ajustes.md) — configuración jerárquica.
8. [Comandos de consola](08-comandos-de-consola.md) — administración desde la terminal.
9. [Despliegue](09-despliegue.md) — Docker y binario nativo.
10. [Operación y mantenimiento](10-operacion-y-mantenimiento.md) — el día a día.

## Sobre el proyecto

Nexo FP forma parte del proyecto de innovación educativa REASOL (PIN-219/23 y PIN-354/24), financiado
por la Consejería de Desarrollo Educativo y Formación Profesional de la Junta de Andalucía. Se distribuye
bajo licencia [AGPL-3.0](http://www.gnu.org/licenses/agpl.html).
