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

**La lógica vive en la base de datos, no en el PHP.**
El PHP consulta y presenta. Cualquier cambio en qué datos mostrar, qué listados cruzar o qué campos incluir se resuelve en la base de datos. El código no se toca.

**DNI como clave única de cruce.**
Toda relación entre tablas usa el DNI como nexo. Es el identificador que permite cruzar padrones, listados externos, historial electoral y cualquier fuente de datos futura.

**El padrón es acumulativo.**
Nunca se da de baja a un graduado. El padrón crece elección a elección sumando nuevos habilitados. Hoy supera los 20.000 registros entre ambos padrones.

**Los listados externos se incorporan sin tocar el código.**
Fuentes de datos adicionales (sedes laborales, municipios, sindicatos, colegios profesionales, etc.) se suben a la base tuneados por el administrador con DNI obligatorio. Una tabla de metadatos (`catalogo`) define qué campos mostrar de cada fuente. El PHP presenta lo que el catálogo le indica.

**Todo exportable a Excel.**
Cualquier listado visible por pantalla puede descargarse. Las vistas de consulta se diseñan planas y aptas para exportación directa.

---

## Estructura de la base de datos

La base de datos se organiza en cinco capas:

**Personas:** tabla maestra acumulativa de individuos. Un registro por graduado, DNI como clave única.

**Padrones:** `padron_cd` y `padron_cp` son independientes. Contienen los DNIs habilitados para cada proceso electoral. No son subconjuntos uno del otro.

**Catálogos:** referentes, espacios políticos, carreras y lugares de trabajo como entidades normalizadas.

**Listados externos:** fuentes de datos adicionales cruzadas por DNI. Se suben completos (no solo los que matchean hoy) porque el padrón crece y un registro que hoy no matchea puede matchear en el futuro.

**Participación electoral:** historial de votos por DNI y elección. Reemplaza las columnas `voto17`, `voto19`, `voto21` embebidas en el padrón actual.

---

## Tabla `catalogo`

Define qué campos mostrar en el perfil de un graduado y en los listados. Cada fila especifica: tabla de origen, nombre del campo, orden de presentación, y si aplica al padrón CD, al padrón CP, o a ambos (flags booleanos `cd` y `cp`).

Agregar un nuevo campo o fuente de datos es una operación sobre esta tabla. El PHP no se modifica.

---

## Incorporación de listados externos

Para incorporar un nuevo listado externo (por ejemplo, afiliados a un sindicato):

1. El administrador prepara el archivo con los campos requeridos.
2. Lo sube a la base de datos como tabla nueva.
3. Registra en `catalogo` los campos que deben mostrarse.

**Campos obligatorios en todo listado externo:**
- `dni` — clave de cruce con la tabla de personas.
- `nombre` y `apellido` — para verificación manual cuando el DNI no matchea.

El listado se sube completo, no filtrado. Los registros que no matchean hoy pueden matchear en elecciones futuras.

---

## Estructura del repositorio

```
/
├── README.md                   # Este archivo
├── docs/                       # Documentación del proyecto
│   ├── analisis_bbdd.md        # Análisis de la base de datos actual
│   └── propuesta_bbdd.md       # Propuesta de nueva base de datos (Paso 2)
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
| Propuesta de nueva base de datos | 🔄 En curso |
| Consulta Padrón — desarrollo | ⏳ Pendiente |
| Fiscalización — desarrollo | ⏳ Pendiente |

---

## Documentación relacionada

- [`docs/analisis_bbdd.md`](docs/analisis_bbdd.md) — Relevamiento y diagnóstico de la base actual, problemas identificados y decisiones de diseño acordadas.
- [`docs/propuesta_bbdd.md`](docs/propuesta_bbdd.md) — Propuesta de nueva base de datos con DDL comentado.
