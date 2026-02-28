# Fiscalizar

Sistema de gestión de padrones y fiscalización electoral para la Facultad de Ciencias Sociales de la Universidad de Buenos Aires (UBA).

---

## Descripción general

La Facultad de Ciencias Sociales realiza dos procesos electorales independientes:

- **Elección de Consejo Directivo (CD):** habilita a graduados de todas las carreras de la facultad.
- **Elección de Ciencia Política (CP):** habilita a graduados de esa carrera y a docentes auxiliares, que pueden ser graduados de otras facultades o de otras carreras de Sociales.

Este sistema gestiona los padrones de ambos procesos, permite cruzar y analizar vínculos con referentes, espacios políticos y lugares de trabajo, registra la participación histórica en elecciones anteriores, y en su etapa final incorpora el registro de votos en tiempo real durante el día de la elección.

El sistema actual fue superado por el crecimiento del proyecto y este repositorio representa su reescritura completa, con una arquitectura pensada para escalar.

---

## Etapas del proyecto

**Consulta Padrón** *(corto plazo)*
Sistema web de consulta y análisis del padrón de graduados. Permite filtrar por apellido, carrera, referente, espacio político y lugar de trabajo. Muestra el perfil completo de cada graduado con todos sus vínculos. Permite exportar cualquier listado a Excel.

**Upgrade** *(mediano plazo)*
Ampliación del modelo de datos y las funcionalidades de consulta y reporte.

**Fiscalización** *(largo plazo)*
Módulo electoral completo: registro de elecciones, mesas, fiscales y votos en tiempo real. Permite registrar procesos electorales pasados y futuros.

---

## Tecnología

| Componente | Tecnología |
|---|---|
| Lenguaje backend | PHP sin frameworks |
| Base de datos | MySQL / MariaDB |
| Frontend | HTML + CSS + JavaScript nativo |
| Servidor | Hosting compartido Wiroos – Plan Personal |
| Dominio | [fiscalizar.com.ar](http://fiscalizar.com.ar/) |
| Control de versiones | Git + GitHub |

---

## Principios de diseño

**DNI como clave única de cruce.**
Toda relación entre tablas usa el DNI como nexo. Es el identificador que permite cruzar padrones, tablas de referentes, historial electoral y cualquier fuente de datos nueva.

**Los padrones se mantienen puros.**
`padron_cd` y `padron_cp` se cargan tal como los entrega la facultad, con todos sus campos originales. No se modifican ni normalizan. Son la fuente de verdad oficial.

**`personas` es el núcleo de consolidación.**
Contiene un registro único por DNI (sin duplicados entre padrones) con apellido y nombre. Es el punto de joineo de todas las tablas por DNI.

**El padrón es acumulativo.**
Nunca se da de baja a un graduado. Los padrones crecen elección a elección sumando nuevos habilitados. Hoy el padrón CD supera los 20.000 registros.

**La lógica vive en las vistas, no en el PHP.**
El PHP hace SELECT contra vistas predefinidas. Cualquier cambio en qué datos cruzar o mostrar se resuelve modificando una vista. El código no se toca.

**Todo exportable a Excel.**
Las vistas se diseñan planas y limpias para que cualquier listado visible por pantalla pueda descargarse directamente sin transformaciones.

**Todas las tablas se administran igual.**
No hay distinción entre tablas "internas" y "externas". Todas las tablas llegan de alguna fuente, son tuneadas por el administrador y subidas a la base. El sistema las consume a todas de la misma manera, joineando por DNI.

---

## Estructura de la base de datos

### Núcleo
- **`personas`** — DNI, apellido, nombre. Un registro por DNI, sin duplicados. Nunca se elimina un registro.

### Padrones
- **`padron_cd`** — Padrón oficial de Consejo Directivo, tal como lo entrega la facultad.
- **`padron_cp`** — Padrón oficial de Ciencia Política, tal como lo entrega la facultad.

Los padrones no son subconjuntos uno del otro. Un DNI puede aparecer en uno, en el otro, o en ambos.

### Catálogos
- **`referentes`** — Lista de referentes políticos con apellido y nombre separados.
- **`partidos`** — Espacios políticos.
- **`trabajos`** — Lugares de trabajo. Incluye categorías como DOCENTE, NO DOCENTE, ADMINISTRATIVO.
- **`carreras`** — Las 5 carreras de la facultad.

### Relaciones
- **`referentes_graduado`** — Vincula cada DNI con hasta 3 referentes (límite firme e histórico).
- **`elecciones`** — Catálogo de procesos electorales pasados y futuros.
- **`participacion_electoral`** — Historial de participación: qué DNI votó en qué elección.

### Tablas adicionales
Cualquier tabla nueva (sede laboral, municipio, sindicato, etc.) se agrega con DNI como campo obligatorio de cruce. Se incorpora a las vistas cuando corresponde.

### Vistas principales
- **`vista_padron_cd`** — Perfil completo de cada habilitado para CD, con todos los datos joineados por DNI.
- **`vista_padron_cp`** — Ídem para CP.

---

## Sistema de login

**Consulta Padrón** tendrá su propio sistema de login con niveles de acceso diferenciados. Cada nivel determina qué campos y qué operaciones puede ver cada usuario. Se diseña en la etapa de desarrollo de Consulta Padrón.

**Fiscalización** tendrá un sistema de login separado e independiente, diseñado en esa etapa.

---

## Incorporación de tablas nuevas

Cuando se incorpora una nueva fuente de datos (por ejemplo, afiliados a un sindicato):

1. El administrador obtiene el listado, lo analiza y lo tunea.
2. Lo sube a la base como tabla nueva.
3. Agrega los campos relevantes a las vistas correspondientes.

**Campos obligatorios en toda tabla nueva:**
- `dni` — clave de cruce con `personas`.
- `apellido` y `nombre` — para verificación manual cuando el DNI no matchea.

La tabla se sube completa, no filtrada. Los registros que hoy no matchean con ningún padrón pueden matchear en el futuro cuando ese DNI sea incorporado.

---

## Estructura del repositorio

```
/
├── README.md                   # Este archivo
├── docs/                       # Documentación del proyecto
│   ├── analisis_bbdd.md        # Análisis de la base de datos actual
│   └── propuesta_bbdd.md       # Propuesta de nueva base de datos
├── sql/                        # Scripts SQL
│   ├── estructura/             # DDL: creación de tablas y vistas
│   └── migracion/              # Scripts de migración desde la base anterior
└── consulta_padron/            # Código fuente de la primera etapa
```

> El desarrollo activo ocurre en subcarpetas. La raíz del dominio mantiene el sistema anterior hasta que la nueva versión esté lista para reemplazarlo.

---

## Estado del proyecto

| Etapa | Estado |
|---|---|
| Análisis de base de datos actual | ✅ Completo |
| Propuesta de nueva base de datos | ✅ Completo |
| Consulta Padrón — desarrollo | ⏳ Pendiente |
| Fiscalización — desarrollo | ⏳ Pendiente |

---

## Documentación relacionada

- [`docs/analisis_bbdd.md`](docs/analisis_bbdd.md) — Relevamiento y diagnóstico de la base actual, problemas identificados y decisiones de diseño acordadas.
- [`docs/propuesta_bbdd.md`](docs/propuesta_bbdd.md) — Propuesta de nueva base de datos con descripción de tablas, relaciones y vistas.
