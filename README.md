# Fiscalizar

![Estado](https://img.shields.io/badge/estado-en_desarrollo-blue)
![Base de datos](https://img.shields.io/badge/base_de_datos-migrada-green)
![Etapa](https://img.shields.io/badge/etapa-consulta_padron-blue)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![MariaDB](https://img.shields.io/badge/MariaDB-10.6-4479A1)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3)

Sistema de gestion de padrones y fiscalizacion electoral para la Facultad de Ciencias Sociales de la Universidad de Buenos Aires (UBA).

---

## Descripcion general

La Facultad de Ciencias Sociales realiza dos procesos electorales independientes:

- **Eleccion de Consejo Directivo (CD):** habilita a graduados de todas las carreras de la facultad.
- **Eleccion de Ciencia Politica (CP):** habilita a graduados de esa carrera y a docentes auxiliares, que pueden ser graduados de otras facultades o de otras carreras de Sociales.

Este sistema gestiona los padrones de ambos procesos, permite cruzar y analizar vinculos con referentes, espacios politicos y lugares de trabajo, registra la participacion historica en elecciones anteriores, y en su etapa final incorpora el registro de votos en tiempo real durante el dia de la eleccion.

---

## Etapas del proyecto

**Consulta Padron** — en desarrollo

Sistema web de consulta y analisis del padron de graduados. Permite filtrar por apellido, carrera, referente, espacio politico y lugar de trabajo. Muestra el perfil completo de cada graduado con todos sus vinculos. Permite exportar cualquier listado a Excel.

**Upgrade** — pendiente

Ampliacion del modelo de datos y las funcionalidades de consulta y reporte.

**Fiscalizacion** — pendiente

Modulo electoral completo: registro de elecciones, mesas, fiscales y votos en tiempo real.

---

## Tecnologia

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.1 sin frameworks |
| Base de datos | MariaDB 10.6 |
| Frontend | Bootstrap 5, HTML, JavaScript nativo |
| Exportacion Excel | PhpSpreadsheet via Composer |
| Servidor | Hosting compartido Wiroos — Plan Personal |
| Dominio | fiscalizar.com.ar |
| Control de versiones | Git + GitHub |

---

## Bases de datos

| Base | Rol |
|---|---|
| fiscaliz_padron | Base nueva. Esquema rediseñado. Desarrollo activo. |
| fiscaliz_fiscalizar | Base anterior. Solo lectura. Fuente de migracion. |
| fiscaliz_graduados | Base de trabajo 2024. Solo lectura. Fuente de migracion. |

Usuario de desarrollo: fiscaliz_dev con acceso completo a fiscaliz_padron.

---

## Principios de diseño

**DNI como clave unica de cruce.** Toda relacion entre tablas usa el DNI como nexo.

**Los padrones se mantienen puros.** padron_cd y padron_cp se cargan tal como los entrega la facultad, con todos sus campos originales.

**personas es el nucleo de consolidacion.** Un registro unico por DNI. Es el punto de joineo de todas las tablas.

**El padron es acumulativo.** Nunca se da de baja a un graduado. Los padrones crecen eleccion a eleccion.

**La logica vive en las vistas, no en el PHP.** El PHP hace SELECT contra vista_padron_cd y vista_padron_cp. Agregar una tabla nueva o una eleccion nueva es una operacion sobre las vistas. El codigo no se toca.

**Solo se registran los que votaron.** participacion_electoral contiene unicamente los DNIs que votaron en cada eleccion. Quien no figura, no voto.

**Todo exportable a Excel.** Las vistas se diseñan planas y limpias para exportacion directa. El PHP construye el Excel dinamicamente desde las columnas del resultado.

**Todas las tablas se administran igual.** El administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.

---

## Sistema de login

**Consulta Padron** tiene su propio sistema de login con tres niveles de acceso: consulta, admin y superadmin. Los usuarios viven en la tabla usuarios de fiscaliz_padron.

**Fiscalizacion** tendra un sistema de login separado e independiente, diseñado en esa etapa.

---

## Estructura del repositorio

```
fiscalizar/
├── README.md
├── .gitignore
├── docs/
│   ├── analisis_bbdd.md
│   ├── propuesta_bbdd.md
│   ├── instalacion.md
│   └── convenciones.md
├── sql/
│   ├── estructura/
│   │   └── fiscaliz_padron.sql
│   └── migracion/
│       └── migracion.md
└── consulta_padron/
    ├── README.md
    ├── composer.json
    ├── index.php
    ├── config/
    │   ├── db.php
    │   └── db.example.php
    ├── includes/
    │   ├── auth.php
    │   ├── navbar.php
    │   ├── footer.php
    │   ├── funciones.php
    │   └── excel.php
    ├── modulos/
    │   ├── login/
    │   │   └── login.php
    │   ├── buscador/
    │   │   └── buscador.php
    │   ├── listados/
    │   │   └── listados.php
    │   ├── filtros/
    │   │   └── filtros.php
    │   ├── abm_referentes/
    │   │   └── abm_referentes.php
    │   ├── abm_partidos/
    │   │   └── abm_partidos.php
    │   ├── abm_trabajos/
    │   │   └── abm_trabajos.php
    │   ├── abm_personas/
    │   │   └── abm_personas.php
    │   └── abm_usuarios/
    │       └── abm_usuarios.php
    └── assets/
        ├── css/
        │   └── estilos.css
        └── js/
            └── main.js
```

---

## Estado del proyecto

| Etapa | Estado |
|---|---|
| Analisis de base de datos anterior | Completo |
| Diseño de nueva base de datos | Completo |
| Migracion de datos | Completo — pendiente validacion profunda antes de produccion |
| Instalacion y configuracion local | Completo |
| Consulta Padron — desarrollo | En curso |
| Fiscalizacion — desarrollo | Pendiente |

---

## Documentacion

- [docs/analisis_bbdd.md](docs/analisis_bbdd.md) — Relevamiento y diagnostico de la base anterior.
- [docs/propuesta_bbdd.md](docs/propuesta_bbdd.md) — Diseño completo del nuevo esquema.
- [docs/instalacion.md](docs/instalacion.md) — Instrucciones para levantar el proyecto en local.
- [docs/convenciones.md](docs/convenciones.md) — Convenciones de codigo del proyecto.
- [sql/estructura/fiscaliz_padron.sql](sql/estructura/fiscaliz_padron.sql) — DDL completo de fiscaliz_padron.
- [sql/migracion/migracion.md](sql/migracion/migracion.md) — Log del proceso de migracion.
- [consulta_padron/README.md](consulta_padron/README.md) — Diseño de la etapa Consulta Padron.
