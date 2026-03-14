# Fiscalizar

Sistema de gestion de padrones y fiscalizacion electoral para la Facultad de Ciencias Sociales de la Universidad de Buenos Aires (UBA).

---

## Descripcion general

La Facultad de Ciencias Sociales realiza dos procesos electorales independientes:

- **Eleccion de Consejo Directivo (CD):** habilita a graduados de todas las carreras de la facultad.
- **Eleccion de Ciencia Politica (CP):** habilita a graduados de esa carrera y a docentes auxiliares, que pueden ser graduados de otras facultades o de otras carreras de Sociales.

Este sistema gestiona los padrones de ambos procesos, permite cruzar y analizar vinculos con referentes, espacios politicos y lugares de trabajo, registra la participacion historica en elecciones anteriores, y en su etapa final incorpora el registro de votos en tiempo real durante el dia de la eleccion.

El sistema anterior fue superado por el crecimiento del proyecto y este repositorio representa su reescritura completa, con una arquitectura pensada para escalar.

---

## Etapas del proyecto

**Consulta Padron** *(corto plazo)*
Sistema web de consulta y analisis del padron de graduados. Permite filtrar por apellido, carrera, referente, espacio politico y lugar de trabajo. Muestra el perfil completo de cada graduado con todos sus vinculos. Permite exportar cualquier listado a Excel.

**Upgrade** *(mediano plazo)*
Ampliacion del modelo de datos y las funcionalidades de consulta y reporte.

**Fiscalizacion** *(largo plazo)*
Modulo electoral completo: registro de elecciones, mesas, fiscales y votos en tiempo real.

---

## Tecnologia

| Componente | Tecnologia |
|---|---|
| Lenguaje backend | PHP sin frameworks |
| Base de datos | MySQL / MariaDB 10.6 |
| Frontend | HTML + CSS + JavaScript nativo |
| Servidor | Hosting compartido Wiroos — Plan Personal |
| Dominio | fiscalizar.com.ar |
| Control de versiones | Git + GitHub |

---

## Bases de datos

| Base | Rol |
|---|---|
| `fiscaliz_padron` | Base nueva. Esquema rediseñado. Desarrollo activo. |
| `fiscaliz_fiscalizar` | Base anterior. Solo lectura. Fuente de migracion. |
| `fiscaliz_graduados` | Base de trabajo 2024. Solo lectura. Fuente de migracion. |

Usuario de desarrollo: `fiscaliz_dev` con acceso completo a `fiscaliz_padron`.

---

## Principios de diseño

**DNI como clave unica de cruce.**
Toda relacion entre tablas usa el DNI como nexo.

**Los padrones se mantienen puros.**
`padron_cd` y `padron_cp` se cargan tal como los entrega la facultad, con todos sus campos originales.

**`personas` es el nucleo de consolidacion.**
Un registro unico por DNI. Es el punto de joineo de todas las tablas.

**El padron es acumulativo.**
Nunca se da de baja a un graduado. Los padrones crecen eleccion a eleccion.

**La logica vive en las vistas, no en el PHP.**
El PHP hace SELECT contra `vista_padron_cd` y `vista_padron_cp`. Agregar una tabla nueva o una eleccion nueva es una operacion sobre las vistas. El codigo no se toca.

**Solo se registran los que votaron.**
`participacion_electoral` contiene unicamente los DNIs que votaron en cada eleccion. Quien no figura, no voto.

**Todo exportable a Excel.**
Las vistas se diseñan planas y limpias para exportacion directa. El PHP construye el Excel dinamicamente desde las columnas del resultado.

**Todas las tablas se administran igual.**
No hay distincion entre tablas internas y externas. El administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.

**Catalogo de carreras cerrado con valor explicito para SIN DATO.**
id=99 / sigla=SD para auxiliares de otras facultades. El id 6 queda reservado para una eventual nueva carrera.

---

## Sistema de login

**Consulta Padron** tiene su propio sistema de login con tres niveles de acceso: consulta, admin y superadmin. Los usuarios viven en la tabla `usuarios` de `fiscaliz_padron`.

**Fiscalizacion** tendra un sistema de login separado e independiente, diseñado en esa etapa.

---

## Estructura del repositorio

```
/
├── README.md                       # Este archivo
├── docs/
│   ├── analisis_bbdd.md            # Analisis de la base de datos anterior
│   └── propuesta_bbdd.md           # Diseño de la nueva base de datos
├── sql/
│   ├── estructura/
│   │   └── fiscaliz_padron.sql     # DDL completo de la base
│   └── migracion/
│       └── migracion.md            # Log del proceso de migracion
└── consulta_padron/
    └── README.md                   # Diseño de la primera etapa
```

---

## Estado del proyecto

| Etapa | Estado |
|---|---|
| Analisis de base de datos anterior | ✅ Completo |
| Diseño de nueva base de datos | ✅ Completo |
| Migracion de datos | ✅ Completo (pendiente validacion profunda antes de produccion) |
| Consulta Padron — desarrollo | 🔄 En curso |
| Fiscalizacion — desarrollo | ⏳ Pendiente |

---

## Documentacion relacionada

- [`docs/analisis_bbdd.md`](docs/analisis_bbdd.md) — Relevamiento y diagnostico de la base anterior, problemas identificados y decisiones de diseño acordadas.
- [`docs/propuesta_bbdd.md`](docs/propuesta_bbdd.md) — Diseño completo del nuevo esquema con descripcion de tablas, relaciones, vistas y estado de carga de datos.
- [`sql/estructura/fiscaliz_padron.sql`](sql/estructura/fiscaliz_padron.sql) — DDL completo de `fiscaliz_padron`. Incluye tablas productivas, staging y vistas.
- [`sql/migracion/migracion.md`](sql/migracion/migracion.md) — Log detallado del proceso de migracion con decisiones tomadas, problemas encontrados y conteos finales.
- [`consulta_padron/README.md`](consulta_padron/README.md) — Diseño de la etapa Consulta Padron: modulos, routing, autenticacion, exportacion y convenciones de codigo.
