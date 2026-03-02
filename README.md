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
Módulo electoral completo: registro de elecciones, mesas, fiscales y votos en tiempo real.

---

## Tecnología

| Componente | Tecnología |
|---|---|
| Lenguaje backend | PHP sin frameworks |
| Base de datos | MySQL / MariaDB 10.6 |
| Frontend | HTML + CSS + JavaScript nativo |
| Servidor | Hosting compartido Wiroos – Plan Personal |
| Dominio | [fiscalizar.com.ar](http://fiscalizar.com.ar/) |
| Control de versiones | Git + GitHub |

---

## Bases de datos

| Base | Rol |
|---|---|
| `fiscaliz_padron` | Base nueva. Esquema rediseñado. Desarrollo activo. |
| `fiscaliz_fiscalizar` | Base anterior. Solo lectura. Fuente de migración. |
| `fiscaliz_graduados` | Base de trabajo 2024. Solo lectura. Fuente de migración. |

Usuario de desarrollo: `fiscaliz_dev` con acceso completo a `fiscaliz_padron`.

---

## Principios de diseño

**DNI como clave única de cruce.**
Toda relación entre tablas usa el DNI como nexo.

**Los padrones se mantienen puros.**
`padron_cd` y `padron_cp` se cargan tal como los entrega la facultad, con todos sus campos originales.

**`personas` es el núcleo de consolidación.**
Un registro único por DNI. Es el punto de joineo de todas las tablas.

**El padrón es acumulativo.**
Nunca se da de baja a un graduado. Los padrones crecen elección a elección.

**La lógica vive en las vistas, no en el PHP.**
El PHP hace SELECT contra `vista_padron_cd` y `vista_padron_cp`. Agregar una tabla nueva o una elección nueva es una operación sobre las vistas. El código no se toca.

**Solo se registran los que votaron.**
`participacion_electoral` contiene únicamente los DNIs que votaron en cada elección. Quien no figura, no votó.

**Todo exportable a Excel.**
Las vistas se diseñan planas y limpias para exportación directa. El PHP construye el Excel dinámicamente desde las columnas del resultado.

**Todas las tablas se administran igual.**
No hay distinción entre tablas internas y externas. El administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.

---

## Sistema de login

**Consulta Padrón** tendrá su propio sistema de login con niveles de acceso diferenciados. Se diseña en la etapa de desarrollo de Consulta Padrón.

**Fiscalización** tendrá un sistema de login separado e independiente, diseñado en esa etapa.

---

## Estructura del repositorio

```
/
├── README.md                   # Este archivo
├── docs/                       # Documentación del proyecto
│   ├── analisis_bbdd.md        # Análisis de la base de datos anterior
│   └── propuesta_bbdd.md       # Diseño de la nueva base de datos
├── sql/                        # Scripts SQL
│   ├── estructura/             # DDL: fiscaliz_padron.sql
│   └── migracion/              # Scripts de migración desde bases anteriores
└── consulta_padron/            # Código fuente de la primera etapa
```

---

## Estado del proyecto

| Etapa | Estado |
|---|---|
| Análisis de base de datos anterior | ✅ Completo |
| Diseño de nueva base de datos | ✅ Completo |
| Migración de datos | ✅ Completo (pendiente validación profunda antes de producción) |
| Consulta Padrón — desarrollo | 🔄 En curso |
| Fiscalización — desarrollo | ⏳ Pendiente |

---

## Documentación relacionada

- [`docs/analisis_bbdd.md`](docs/analisis_bbdd.md) — Relevamiento y diagnóstico de la base anterior, problemas identificados y decisiones de diseño acordadas.
- [`docs/propuesta_bbdd.md`](docs/propuesta_bbdd.md) — Diseño completo del nuevo esquema con descripción de tablas, relaciones, vistas y estado de carga de datos.
