# Datos de demostración

Este documento describe los datos que carga el comando de fixtures de demostración y cómo utilizarlos.

## Cargar los fixtures

> ⚠️ Todas las opciones borran los datos existentes antes de insertar los de demostración,
> por lo que es seguro ejecutarlas varias veces.

### Opción A — Scripts de demostración (binario nativo, recomendado)

En el paquete del binario nativo basta con usar los scripts `demo.*` en lugar de los `start.*`.
Son idénticos al arranque normal pero cargan los fixtures automáticamente:

```bash
./demo.sh                 # Linux / macOS
demo.bat                  # Windows CMD
.\demo.ps1                # Windows PowerShell
```

En macOS también puedes hacer **doble clic en `demo.command`** (la primera vez: clic derecho → *Abrir*).
Aceptan un puerto opcional, igual que los scripts de arranque (`./demo.sh 9000`).

### Opción B — Variable de entorno

Establece `LOAD_FIXTURES=true` antes de arrancar la aplicación:

**Docker** — en tu fichero `.env.local` (copiado de `.env.example`) cambia el valor de
la variable que ya existe; no añadas una línea nueva, porque una clave duplicada haría
que Docker Compose use la última aparición y podría seguir valiendo `false`. Recuerda
exportar `COMPOSE_ENV_FILES=.env.local` (o usar `--env-file .env.local`) para que Compose
lo lea:

```dotenv
LOAD_FIXTURES=true
```

**Binario nativo (Linux / macOS):**

```bash
LOAD_FIXTURES=true ./start.sh
```

**Binario nativo (Windows PowerShell):**

```powershell
$env:LOAD_FIXTURES = "true"; .\start.ps1
```

### Opción C — Manual (entorno de desarrollo)

```bash
make fixtures
```

> El comando equivalente es `php bin/console doctrine:fixtures:load --no-interaction --append`.
> El flag `--append` es obligatorio por dos motivos: el esquema tiene una FK circular entre
> `educational_centre` y `academic_year` que el purger de Doctrine no puede resolver (la limpieza
> se realiza dentro del propio fixture en el orden correcto), y `setting_definition` es una tabla
> de datos de referencia sembrada por las migraciones que no debe borrarse al recargar demos.

## Credenciales de acceso

Existe un usuario administrador global independiente de los centros:

| Username | Contraseña | Rol |
|---|---|---|
| `admin` | `admin` | Administrador global (sin centro asignado) |

El resto de docentes usan la contraseña **`ejemplo`**. Por ejemplo:

| Username | Contraseña |
|---|---|
| `rafael.exposito` | `ejemplo` |
| `mariajose.alvarez` | `ejemplo` |

---

## Centro 1 — IES Ada Lovelace (Linares)

**Código de centro:** `23006123`  
**Año académico activo:** 2025-2026

### Oferta formativa

| Familia | Título completo | Abrev. | Grupos |
|---|---|---|---|
| Informática y Comunicaciones | CFGM Sistemas Microinformáticos y Redes | SMR | 1ºSMR-A, 2ºSMR-A |
| Informática y Comunicaciones | CFGS Desarrollo de Aplicaciones Web | DAW | 1ºDAW-M, 1ºDAW-T (7 alumnos), 2ºDAW-M, 2ºDAW-T (7 alumnos) |
| Sanidad | CFGS Audiología Protésica | AP | 1ºAP-A, 2ºAP-A |
| Sanidad | CFGS Higiene Bucodental | HB | 1ºHB-A, 2ºHB-A |

### Docentes

| Username | Nombre completo | Rol |
|---|---|---|
| `rafael.exposito` | Rafael Expósito Moreno | Administrador global + Administrador de centro |
| `carmen.diaz` | Carmen Díaz Jiménez | Administrador de centro |
| `francisco.molina` | Francisco Javier Molina Ruiz | Jefe de familia — Informática y Comunicaciones |
| `isabel.lozano` | Isabel Lozano Herrera | Jefe de familia — Sanidad |
| `maria.garcia` | María Dolores García Fernández | Coordinadora — SMR |
| `diego.romero` | Diego Romero Vega | Coordinador — DAW |
| `manuel.perez` | Manuel Pérez Blanco | Coordinador — Audiología Protésica |
| `roberto.guerrero` | Roberto Guerrero Campos | Coordinador — Higiene Bucodental |
| `beatriz.alonso` | Beatriz Alonso Serrano | Docente de enlace (empresas 1–6) |
| `rodrigo.fuentes` | Rodrigo Fuentes Parra | Docente de enlace (empresas 1–6) |
| `elena.caballero` | Elena Caballero Ruiz | Docente de enlace (empresas 7–9) |
| `julio.medina` | Julio Medina Torres | Docente de enlace (empresas 7–9) |
| `sofia.delgado` | Sofía Delgado Iglesias | Docente de enlace (empresas 10–12) |
| `marcos.herrero` | Marcos Herrero Vidal | Docente de enlace (empresas 10–12) |
| `alberto.cabrera` | Alberto Cabrera García | Docente |
| `nuria.lopez` | Nuria López Morales | Docente |
| `javier.ortega` | Javier Ortega Bravo | Docente |
| `anabelen.castro` | Ana Belén Castro Fuentes | Docente |
| `tomas.vazquez` | Tomás Vázquez Acosta | Docente |
| `rosamaria.serrano` | Rosa María Serrano Díaz | Docente |
| `fernando.ibanez` | Fernando Ibáñez Cano | Docente |
| `marta.ramos` | Marta Ramos Palacios | Docente |
| `sergio.gallego` | Sergio Gallego Nieto | Docente |
| `veronica.mora` | Verónica Mora Espinosa | Docente |
| `pablo.aguilar` | Pablo Aguilar Blanco | Docente |
| `concepcion.munoz` | Concepción Muñoz Aranda | Docente |
| `alvaro.suarez` | Álvaro Suárez Paredes | Docente |
| `patricia.rubio` | Patricia Rubio Fernández | Docente |
| `luis.carrasco` | Luis Carrasco Reyes | Docente |
| `sandra.dominguez` | Sandra Domínguez Orozco | Docente |

### Empresas

| # | Nombre | CIF | Ciudad | Sector | Docente(s) de enlace |
|---|---|---|---|---|---|
| 1 | Repsol Química S.A. | B12300001 | Linares | Industria/IT | beatriz.alonso, rodrigo.fuentes |
| 2 | Indra Sistemas S.L. | B12300002 | Linares | IT | beatriz.alonso, rodrigo.fuentes |
| 3 | Telco Jaén S.L. | B12300003 | Linares | Telecom | beatriz.alonso, rodrigo.fuentes |
| 4 | Informática Linares S.L. | B12300004 | Linares | IT | beatriz.alonso, rodrigo.fuentes |
| 5 | DataSystems Jaén S.L. | B12300005 | Linares | IT | beatriz.alonso, rodrigo.fuentes |
| 6 | NetConsulting Sur S.L. | B12300006 | Linares | IT | beatriz.alonso, rodrigo.fuentes |
| 7 | Hospital Comarcal de Linares | B12300007 | Linares | Sanidad | elena.caballero, julio.medina |
| 8 | Clínica Virgen del Carmen S.L. | B12300008 | Linares | Sanidad | elena.caballero, julio.medina |
| 9 | Centro Médico Jaén Norte S.L. | B12300009 | Linares | Sanidad | elena.caballero, julio.medina |
| 10 | Farmacia Morales Cano S.L. | B12300010 | Linares | Farmacia | sofia.delgado, marcos.herrero |
| 11 | Auxiliar Sanitaria Sur S.L. | B12300011 | Linares | Sanidad | sofia.delgado, marcos.herrero |
| 12 | Ortopedia Pérez Garrido S.L. | B12300012 | Linares | Sanidad | sofia.delgado, marcos.herrero |

---

## Centro 2 — IES Monterrubio (Utrera)

**Código de centro:** `41017845`  
**Año académico activo:** 2025-2026

### Oferta formativa

| Familia | Título completo | Abrev. |
|---|---|---|
| Informática y Comunicaciones | CFGM Sistemas Microinformáticos y Redes | SMR |
| Informática y Comunicaciones | CFGS Desarrollo de Aplicaciones Web | DAW |
| Servicios Socioculturales y a la Comunidad | CFGS Integración Social | IS |
| Servicios Socioculturales y a la Comunidad | CFGS Promoción de Igualdad de Género | PIG |
| Imagen Personal | CFGM Peluquería y Cuidados Capilares | PCC |
| Imagen Personal | CFGS Estética y Belleza | EB |

### Docentes

| Username | Nombre completo | Rol |
|---|---|---|
| `mariajose.alvarez` | María José Álvarez García | Administrador global + Administrador de centro |
| `pedro.fernandez` | Pedro Antonio Fernández Rubio | Administrador de centro |
| `rosario.soto` | Rosario Soto Merino | Jefe de familia — Informática y Comunicaciones |
| `dolores.reyes` | Dolores Reyes Álvarez | Jefe de familia — Servicios Socioculturales |
| `antonia.guzman` | Antonia Guzmán Osuna | Jefe de familia — Imagen Personal |
| `ignacio.crespo` | Ignacio Crespo Leal | Coordinador — SMR |
| `piedad.torres` | Piedad Torres Velázquez | Coordinadora — DAW |
| `vicente.roldan` | Vicente Roldán Camacho | Coordinador — Integración Social |
| `carmenrosa.marin` | Carmen Rosa Marín Espejo | Coordinadora — Promoción de la Igualdad de Género |
| `josefa.naranjo` | Josefa Naranjo Hidalgo | Coordinadora — Peluquería y Cuidados Capilares |
| `remedios.calvo` | Remedios Calvo Durán | Coordinadora — Estética y Belleza |
| `bartolome.morales` | Bartolomé Morales Cabello | Docente de enlace (empresas 1–4) |
| `francisca.giron` | Francisca Girón Padilla | Docente de enlace (empresas 1–4) |
| `sebastian.lara` | Sebastián Lara Nieto | Docente de enlace (empresas 5–8) |
| `encarnacion.baena` | Encarnación Baena Vilches | Docente de enlace (empresas 5–8) |
| `manuela.criado` | Manuela Criado Arroyo | Docente de enlace (empresas 9–12) |
| `demetrio.gallardo` | Demetrio Gallardo Cruz | Docente |
| `amelia.fuentes` | Amelia Fuentes Olea | Docente |
| `isidoro.bueno` | Isidoro Bueno Salas | Docente |
| `remedios.ortiz` | Remedios Ortiz Pedrera | Docente |
| `alfonso.serrano` | Alfonso Serrano Rico | Docente |
| `montserrat.cobo` | Montserrat Cobo Rivas | Docente |
| `gonzalo.torres` | Gonzalo Torres Jurado | Docente |
| `esperanza.ruiz` | Esperanza Ruiz Calero | Docente |
| `horacio.lopez` | Horacio López Bravo | Docente |
| `natividad.moreno` | Natividad Moreno Navarro | Docente |
| `dionisio.garcia` | Dionisio García Blanco | Docente |
| `rosalia.campos` | Rosalía Campos Vega | Docente |
| `teodoro.herrero` | Teodoro Herrero Reina | Docente |
| `milagros.jimenez` | Milagros Jiménez Villar | Docente |

### Empresas

| # | Nombre | CIF | Ciudad | Sector | Docente(s) de enlace |
|---|---|---|---|---|---|
| 1 | Accenture Spain S.L. | B41300001 | Sevilla | IT | bartolome.morales, francisca.giron |
| 2 | Comex Informática S.L. | B41300002 | Utrera | IT | bartolome.morales, francisca.giron |
| 3 | Red Eléctrica IT Services S.L. | B41300003 | Sevilla | IT | bartolome.morales, francisca.giron |
| 4 | Eviden Spain S.L. | B41300004 | Sevilla | IT | bartolome.morales, francisca.giron |
| 5 | Grupo Vitalia Sevilla S.L. | B41300005 | Sevilla | Atención sociosanitaria | sebastian.lara, encarnacion.baena |
| 6 | Centro de Día Los Olivos S.L. | B41300006 | Utrera | Atención sociosanitaria | sebastian.lara, encarnacion.baena |
| 7 | Fundación Sevilla Integra | B41300007 | Sevilla | Integración social | sebastian.lara, encarnacion.baena |
| 8 | Servicios Sociales Utrera S.L. | B41300008 | Utrera | Integración social | sebastian.lara, encarnacion.baena |
| 9 | Peluquería Marta García S.L. | B41300009 | Utrera | Imagen personal | manuela.criado |
| 10 | Centro Estético Belleza Sur S.L. | B41300010 | Sevilla | Imagen personal | manuela.criado |
| 11 | Instituto Belleza Hispalense S.L. | B41300011 | Sevilla | Imagen personal | manuela.criado |
| 12 | Spa y Bienestar Guadalquivir S.L. | B41300012 | Utrera | Imagen personal | manuela.criado |

---

## Escenarios de estancias

Cada enseñanza tiene **dos estancias** que cubren distintos momentos del curso.

### Estancia pasada — `FFEOE <ABREV> 2025 (1.er trimestre)`

Fechas: 15/09/2025 – 31/01/2026. Alumnos de **1.º** de la enseñanza.

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| 1–5 | Alumno A–E | DONE | Sí | Prácticas finalizadas |
| — | Alumno F | sin puesto | — | Matriculado en la estancia sin puesto asignado |
| — | Alumno G | sin puesto | — | Matriculado en la estancia sin puesto asignado |

### Estancia actual — `FFEOE <ABREV> 2026 (2.º trimestre)`

Fechas: 01/03/2026 – 30/06/2026. Alumnos de **2.º** de la enseñanza.

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| 1 | — | DRAFT | No | Puesto vacante sin asignar |
| 2 | — | DRAFT | No | Puesto vacante sin asignar |
| 3 | Alumno A | DRAFT | No | Asignado **sin tutor dual docente** → aparece en «Pendientes» |
| 4 | Alumno B | PENDING | No | En prácticas, sin firmar, **fecha límite 25/06** → notificación |
| 5 | Alumno C | PENDING | Sí | En prácticas, firmado |
| 6 | Alumno D | DONE | Sí | Prácticas finalizadas |
| 7 | Alumno E | DONE | Sí | Prácticas finalizadas |
| — | Alumno F | sin puesto | — | Matriculado en la estancia sin puesto asignado |
| — | Alumno G | sin puesto | — | Matriculado en la estancia sin puesto asignado |

### Escenario especial DAW (IES Ada Lovelace)

DAW tiene grupos de mañana (`-M`, 12 alumnos) y de tarde (`-T`, 7 alumnos).

**Estancia pasada** (`FFEOE DAW 2025 (1.er trimestre)`): solo alumnos de 1ºDAW-M, con el mismo patrón que el escenario estándar.

**Estancia actual** (`FFEOE DAW 2026 (2.º trimestre)`): combina 2ºDAW-M y 2ºDAW-T en la misma estancia.

Alumnos de 2ºDAW-M: mismo patrón que el escenario estándar (puestos 1-7 + 2 sin asignar).

Alumnos de 2ºDAW-T:

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| T-1 | Alumno T-A | PENDING | No | En prácticas, sin firmar, **fecha límite 24/06** → notificación |
| T-2 | Alumno T-B | DRAFT | No | Asignado **sin tutor dual docente** → aparece en «Pendientes» |
| — | Alumnos T-C a T-G | sin puesto | — | Matriculados sin puesto asignado aún |

### Información de contacto en empresas

Aproximadamente la mitad de las empresas tienen el campo **Información de contacto** cumplimentado con nombre del responsable, correo electrónico y teléfono, para mostrar el editor de texto enriquecido.
