# Datos de demostración

Este documento describe los datos que carga el comando de fixtures de demostración y cómo utilizarlos.

## Cargar los fixtures

> ⚠️ Ambas opciones borran todos los datos existentes antes de insertar los de demostración,
> por lo que es seguro ejecutarlas varias veces.

### Opción A — Arranque automático (recomendado)

Establece `LOAD_FIXTURES=true` antes de arrancar la aplicación:

**Docker** — añade al fichero `.env`:

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

### Opción B — Manual (entorno de desarrollo)

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

Cada docente de centro usa su **username como contraseña**. Por ejemplo:

| Username | Contraseña |
|---|---|
| `rafael.exposito` | `rafael.exposito` |
| `mariajose.alvarez` | `mariajose.alvarez` |

---

## Centro 1 — IES Ada Lovelace (Linares)

**Código de centro:** `23006123`  
**Año académico activo:** 2025-2026

### Oferta formativa

| Familia | Título completo | Abrev. |
|---|---|---|
| Informática y Comunicaciones | CFGM Sistemas Microinformáticos y Redes | SMR |
| Informática y Comunicaciones | CFGS Administración de Sistemas Informáticos en Red | ASIR |
| Informática y Comunicaciones | CFGS Desarrollo de Aplicaciones Multiplataforma | DAM |
| Informática y Comunicaciones | CFGS Desarrollo de Aplicaciones Web | DAW |
| Sanidad | CFGM Cuidados Auxiliares de Enfermería | CAUE |
| Sanidad | CFGM Emergencias Sanitarias | ES |
| Sanidad | CFGS Higiene Bucodental | HB |
| Sanidad | CFGS Audiología Protésica | AP |

### Docentes

| Username | Nombre completo | Rol |
|---|---|---|
| `rafael.exposito` | Rafael Expósito Moreno | Administrador global + Administrador de centro |
| `carmen.diaz` | Carmen Díaz Jiménez | Administrador de centro |
| `francisco.molina` | Francisco Javier Molina Ruiz | Jefe de familia — Informática y Comunicaciones |
| `maria.garcia` | María Dolores García Fernández | Coordinador — SMR |
| `antonio.navarro` | Antonio Navarro Castillo | Coordinador — ASIR |
| `laura.sanchez` | Laura Sánchez Torres | Coordinador — DAM |
| `diego.romero` | Diego Romero Vega | Coordinador — DAW |
| `isabel.lozano` | Isabel Lozano Herrera | Jefe de familia — Sanidad |
| `manuel.perez` | Manuel Pérez Blanco | Coordinador — CAUE |
| `pilar.martinez` | Pilar Martínez Rueda | Coordinador — Emergencias Sanitarias |
| `roberto.guerrero` | Roberto Guerrero Campos | Coordinador — Higiene Bucodental |
| `cristina.vargas` | Cristina Vargas Morales | Coordinador — Audiología Protésica |
| `beatriz.alonso` | Beatriz Alonso Serrano | Docente de enlace (empresas IT 1–6) |
| `rodrigo.fuentes` | Rodrigo Fuentes Parra | Docente de enlace (empresas IT 1–6) |
| `elena.caballero` | Elena Caballero Ruiz | Docente de enlace (empresas 7–9) |
| `julio.medina` | Julio Medina Torres | Docente de enlace (empresas 7–9) |
| `sofia.delgado` | Sofía Delgado Iglesias | Docente de enlace (empresas SAN 10–12) |
| `marcos.herrero` | Marcos Herrero Vidal | Docente de enlace (empresas SAN 10–12) |
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
| `david.pozo` | David Pozo Santana | Docente |
| `inmaculada.pena` | Inmaculada Peña García | Docente |
| `oscar.cortes` | Óscar Cortés Nieto | Docente |
| `yolanda.jimenez` | Yolanda Jiménez Fuentes | Docente |
| `miguel.flores` | Miguel Ángel Flores Pérez | Docente |
| `lucia.campos` | Lucía Campos Herrero | Docente |
| `enrique.benitez` | Enrique Benítez Castro | Docente |
| `marina.herrera` | Marina Herrera López | Docente |
| `joseluis.pinto` | José Luis Pinto García | Docente |
| `amparo.gomez` | Amparo Gómez Sánchez | Docente |
| `carlos.cano` | Carlos Cano Moreno | Docente |
| `teresa.prieto` | Teresa Prieto Vega | Docente |
| `andres.moya` | Andrés Moya López | Docente |
| `gloria.romero` | Gloria Romero Herrera | Docente |
| `guillermo.ruiz` | Guillermo Ruiz Vidal | Docente |
| `victoria.navarro` | Victoria Navarro Gil | Docente |
| `alejandro.martin` | Alejandro Martín Díaz | Docente |
| `silvia.pacheco` | Silvia Pacheco Ruiz | Docente |
| `eduardo.medina` | Eduardo Medina Vargas | Docente |

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
| `ignacio.crespo` | Ignacio Crespo Leal | Coordinador — SMR |
| `piedad.torres` | Piedad Torres Velázquez | Coordinador — DAW |
| `dolores.reyes` | Dolores Reyes Álvarez | Jefe de familia — Servicios Socioculturales |
| `vicente.roldan` | Vicente Roldán Camacho | Coordinador — Integración Social |
| `carmenrosa.marin` | Carmen Rosa Marín Espejo | Coordinador — Promoción de la Igualdad de Género |
| `antonia.guzman` | Antonia Guzmán Osuna | Jefe de familia — Imagen Personal |
| `josefa.naranjo` | Josefa Naranjo Hidalgo | Coordinador — Peluquería y Cuidados Capilares |
| `remedios.calvo` | Remedios Calvo Durán | Coordinador — Estética y Belleza |
| `bartolome.morales` | Bartolomé Morales Cabello | Docente de enlace (empresas IT 1–4) |
| `francisca.giron` | Francisca Girón Padilla | Docente de enlace (empresas IT 1–4) |
| `sebastian.lara` | Sebastián Lara Nieto | Docente de enlace (empresas SSC 5–8) |
| `encarnacion.baena` | Encarnación Baena Vilches | Docente de enlace (empresas SSC 5–8) |
| `manuela.criado` | Manuela Criado Arroyo | Docente de enlace (empresas IP 9–12) |
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
| `fermin.castillo` | Fermín Castillo Pérez | Docente |
| `olimpia.santana` | Olimpia Santana Durán | Docente |
| `aurelio.gomez` | Aurelio Gómez Márquez | Docente |
| `fatima.palacios` | Fátima Palacios Estrada | Docente |
| `celestino.ramos` | Celestino Ramos Garrido | Docente |
| `azucena.suarez` | Azucena Suárez Montoro | Docente |
| `esteban.maldonado` | Esteban Maldonado Cid | Docente |
| `presentacion.delgado` | Presentación Delgado Cuenca | Docente |
| `wenceslao.cruz` | Wenceslao Cruz Carrillo | Docente |
| `purificacion.aguilar` | Purificación Aguilar Peña | Docente |
| `leopoldo.bravo` | Leopoldo Bravo Solano | Docente |
| `candelaria.munoz` | Candelaria Muñoz Serrano | Docente |
| `ezequiel.toro` | Ezequiel Toro Caballero | Docente |
| `adoracion.haro` | Adoración Haro Gutiérrez | Docente |
| `serafin.vidal` | Serafín Vidal Peña | Docente |
| `leonor.molina` | Leonor Molina Fuentes | Docente |
| `anselmo.perez` | Anselmo Pérez Lozano | Docente |
| `concepcion.barroso` | Concepción Barroso Gil | Docente |
| `baltasar.herrera` | Baltasar Herrera Mena | Docente |
| `amparo.romero` | Amparo Romero Durán | Docente |
| `inocencio.garcia` | Inocencio García Quesada | Docente |
| `visitacion.blanco` | Visitación Blanco Mora | Docente |

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

Cada enseñanza tiene **tres estancias** que cubren distintos momentos del curso:

### Estancia pasada — `FFEOE <ABREV> 2025 (1.er trimestre)`

Fechas: 15/09/2025 – 31/01/2026. Alumnos de **1.º** de la enseñanza.

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| 1–5 | Alumno A–E | DONE | Sí | Prácticas finalizadas |
| — | Alumno F | sin puesto | — | Matriculado en la estancia sin puesto asignado |
| — | Alumno G | sin puesto | — | Matriculado en la estancia sin puesto asignado |

### Estancia actual — `FFEOE <ABREV> 2026 (2.º trimestre)`

Fechas: 01/03/2026 – 20/06/2026. Alumnos de **2.º** de la enseñanza.

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| 1 | — | DRAFT | No | Puesto vacante sin asignar |
| 2 | — | DRAFT | No | Puesto vacante sin asignar |
| 3 | Alumno A | DRAFT | No | Asignado pero pendiente de confirmar |
| 4 | Alumno B | PENDING | No | En prácticas, sin firmar |
| 5 | Alumno C | PENDING | Sí | En prácticas, firmado |
| 6 | Alumno D | DONE | Sí | Prácticas finalizadas |
| 7 | Alumno E | DONE | Sí | Prácticas finalizadas |
| — | Alumno F | sin puesto | — | Matriculado en la estancia sin puesto asignado |
| — | Alumno G | sin puesto | — | Matriculado en la estancia sin puesto asignado |

### Estancia futura — `FFEOE <ABREV> 2026-2027`

Fechas: 15/09/2026 – 31/01/2027. Sin alumnos matriculados aún.

| Puesto | Alumno | Estado | Firmado | Descripción |
|---|---|---|---|---|
| 1 | — | DRAFT | No | Puesto vacante sin asignar |
| 2 | — | DRAFT | No | Puesto vacante sin asignar |
