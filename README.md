# Fiscalizar

Sistema de gestión de padrones y fiscalización electoral para la Facultad de Ciencias Sociales de la Universidad de Buenos Aires (UBA).

---

## Descripción general

Este proyecto nació como una herramienta de consulta sobre el padrón de graduados de la Facultad de Ciencias Sociales de la UBA y de la carrera de Ciencia Política. Con el tiempo creció incorporando el registro de votos en tiempo real durante procesos electorales. El sistema actual fue superado por ese crecimiento y este repositorio representa su reescritura completa, con una arquitectura pensada para escalar.

El sistema se desarrolla en tres etapas:

- **Consulta Padrón** *(corto plazo)*: sistema web que permite consultar, filtrar y cruzar datos del padrón de graduados con información de referentes, espacios políticos y lugares de trabajo.
- **Upgrade** *(mediano plazo)*: ampliación del modelo de datos y las funcionalidades de consulta y reporte.
- **Fiscalización** *(largo plazo)*: incorporación del módulo electoral completo: registro de elecciones, mesas, fiscales y votos en tiempo real.

---

## Tecnología

| Componente | Tecnología |
|---|---|
| Lenguaje backend | PHP (sin frameworks) |
| Base de datos | MySQL / MariaDB |
| Frontend | HTML + CSS + JavaScript nativo |
| Servidor | Hosting compartido Wiroos – Plan Personal |
| Dominio | [fiscalizar.com.ar](http://fiscalizar.com.ar/) |
| Control de versiones | Git + GitHub |

---

## Estructura del repositorio

```
/
├── README.md               # Este archivo
├── docs/                   # Documentación del proyecto
│   ├── analisis_bbdd.md    # Análisis de la base de datos actual
│   └── propuesta_bbdd.md   # Propuesta de nueva base de datos
├── sql/                    # Scripts SQL
│   ├── estructura/         # DDL: creación de tablas
│   └── migracion/          # Scripts de migración desde la base anterior
└── consulta_padron/        # Código fuente de la primera etapa
```

> El desarrollo activo ocurre en subcarpetas. La raíz del dominio mantiene el sistema anterior hasta que la nueva versión esté lista para reemplazarlo.

---

## Base de datos

La base de datos central consolida:

- Padrón de graduados de Ciencias Sociales (todas las carreras)
- Padrón de graduados de Ciencia Política
- Catálogos de referentes, espacios políticos y lugares de trabajo
- Historial de participación electoral (2017, 2019, 2021, 2024)
- Registro en tiempo real de votos por mesa y fiscal

El diseño está normalizado y preparado para soportar múltiples elecciones, múltiples padrones y múltiples referentes por persona.

---

## Alcance electoral

La Facultad de Ciencias Sociales realiza elecciones de Consejo Directivo (CD) y elecciones de carrera (Ciencia Política). Son procesos separados, con padrones distintos, pero comparten la misma base de datos de personas.

---

## Estado del proyecto

| Etapa | Estado |
|---|---|
| Análisis de base de datos actual | ✅ Completo |
| Propuesta de nueva base de datos | 🔄 En curso |
| Consulta Padrón – desarrollo | ⏳ Pendiente |
| Fiscalización – desarrollo | ⏳ Pendiente |

---

## Equipo

Proyecto desarrollado para uso interno de la Facultad de Ciencias Sociales, UBA.
