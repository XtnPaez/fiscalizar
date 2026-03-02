# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar  
**Fecha:** Marzo 2026  
**Etapa:** Paso 2 — Diseño del nuevo esquema  

---

## 1. Principios que guían este diseño

- **DNI como clave única de cruce** entre todas las tablas.
- **Todo InnoDB, todo utf8mb4:** integridad referencial garantizada por el motor, no por el código.
- **La lógica vive en las vistas:** el PHP hace SELECT contra vistas predefinidas y presenta lo que encuentra. No decide qué tablas cruzar ni qué campos mostrar.
- **Los padrones se mantienen puros:** se cargan tal como los entrega la facultad, con todos sus campos originales.
- **Padrones acumulativos:** nunca se elimina un registro. Solo se suman nuevos habilitados con cada elección.
- **Todas las tablas se administran igual:** el administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.
- **Escalabilidad hacia Fiscalización:** el esquema no necesita cambios estructurales para incorporar el módulo electoral en etapas futuras.

---

## 2. Mapa general de tablas

```
NÚCLEO
    personas

PADRONES (puros, tal como los entrega la facultad)
    padron_cd
    padron_cp

CATÁLOGOS
    carreras
    referentes
    partidos
    trabajos

RELACIONES
    referentes_graduado     (DNI ↔ hasta 3 referentes, límite firme e histórico)
    persona_partido         (DNI ↔ espacio político)
    persona_trabajo         (DNI ↔ lugar de trabajo)
    elecciones              (catálogo de procesos electorales)
    participacion_electoral (historial: solo se registran los que votaron)

TABLAS ADICIONALES (una por fuente, DNI obligatorio)
    sede_laboral
    (futuras: sindicato, colegio profesional, afiliación partidaria, etc.)

VISTAS
    vista_padron_cd
    vista_padron_cp
```

---

## 3. Descripción de cada tabla

---

### `personas`
**Rol:** tabla núcleo del esquema. Contiene un registro único por DNI, sin duplicados entre padrones. Es el punto de joineo de todas las tablas. Nunca se elimina un registro.

Un DNI aparece aquí si está en `padron_cd`, en `padron_cp`, o en ambos. Si alguien está en los dos padrones, en `personas` aparece una sola vez.

| Campo | Tipo | Descripción |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria. |
| `apellido` | VARCHAR(120) | Apellido en mayúsculas. |
| `nombre` | VARCHAR(120) | Nombre en mayúsculas. |

---

### `padron_cd`
**Rol:** padrón oficial de Consejo Directivo tal como lo publica la facultad. Acumulativo: solo se agregan registros, nunca se eliminan. No contiene DNIs repetidos internamente, pero puede compartir DNIs con `padron_cp`.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. |
| `dni` | INT UNSIGNED | Clave foránea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padrón oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padrón oficial. |
| `sigla` | VARCHAR(12) | Sigla de la carrera según el padrón oficial. |

---

### `padron_cp`
**Rol:** padrón oficial de Ciencia Política tal como lo publica la facultad. Incluye graduados de CP y docentes auxiliares. El campo `auxiliar` se agrega durante el tuneo previo a la carga. Los auxiliares que no figuren en el padrón oficial se incorporan desde la tabla `auxiliarescp24` en `fiscaliz_graduados`.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. |
| `dni` | INT UNSIGNED | Clave foránea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padrón oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padrón oficial. |
| `auxiliar` | TINYINT(1) | 1 = docente auxiliar, 0 = graduado. |

---

### `carreras`
**Rol:** catálogo de las 5 carreras de la facultad.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `descripcion` | VARCHAR(50) | Nombre completo. |
| `sigla` | VARCHAR(5) | Sigla. Ej: CP, CS, RT, TS, CC. |

---

### `referentes`
**Rol:** catálogo de referentes políticos. Apellido y nombre en campos separados. Regla de separación: todo lo que está después del último espacio es apellido. El registro SIN REFERENTE se mantiene como valor explícito con id=1.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `apellido` | VARCHAR(80) | Apellido del referente. |
| `nombre` | VARCHAR(80) | Nombre del referente. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja lógica. |

---

### `partidos`
**Rol:** catálogo de espacios políticos.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `nombre` | VARCHAR(80) | Nombre del espacio político. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja lógica. |

---

### `trabajos`
**Rol:** catálogo de lugares de trabajo. Incluye las categorías DOCENTE, NO DOCENTE y ADMINISTRATIVO como valores válidos con significado para el sistema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `nombre` | VARCHAR(120) | Nombre del lugar de trabajo o categoría. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja lógica. |

---

### `referentes_graduado`
**Rol:** vincula cada DNI con hasta 3 referentes. El límite de 3 es firme e histórico. NULL indica ausencia de referente en esa posición. Quien no tiene ningún referente no tiene fila en esta tabla.

| Campo | Tipo | Descripción |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foránea a `personas`. |
| `referente_1` | INT | Foránea a `referentes`. NULL si no tiene. |
| `referente_2` | INT | Foránea a `referentes`. NULL si no tiene. |
| `referente_3` | INT | Foránea a `referentes`. NULL si no tiene. |

---

### `persona_partido`
**Rol:** vincula cada DNI con su espacio político. Un partido por persona. Quien no tiene partido no tiene fila en esta tabla. Se llena y actualiza vía ABM del sistema o carga manual.

| Campo | Tipo | Descripción |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foránea a `personas`. |
| `id_partido` | INT | Foránea a `partidos`. |

---

### `persona_trabajo`
**Rol:** vincula cada DNI con su lugar de trabajo. Un trabajo por persona. Quien no tiene trabajo no tiene fila en esta tabla. Se llena y actualiza vía ABM del sistema o carga manual.

| Campo | Tipo | Descripción |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foránea a `personas`. |
| `id_trabajo` | INT | Foránea a `trabajos`. |

---

### `elecciones`
**Rol:** catálogo de procesos electorales pasados y futuros. Solo una elección puede estar activa por tipo en simultáneo.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `nombre` | VARCHAR(80) | Ej: Elección CD 2024. |
| `tipo` | ENUM('cd','cp') | Tipo de proceso electoral. |
| `anio` | YEAR | Año de la elección. |
| `activa` | TINYINT(1) | 1 = elección en curso. |

**Elecciones cargadas:**

| id | nombre | tipo | anio |
|---|---|---|---|
| 1 | Elección CP 2017 | cp | 2017 |
| 2 | Elección CP 2019 | cp | 2019 |
| 3 | Elección CD 2021 | cd | 2021 |
| 4 | Elección CP 2021 | cp | 2021 |
| 5 | Elección CD 2024 | cd | 2024 |
| 6 | Elección CP 2024 | cp | 2024 |

---

### `participacion_electoral`
**Rol:** historial de participación. Solo se registran los que votaron. Quien no figura en esta tabla para una elección determinada, no votó. Agregar una elección nueva es insertar filas aquí, no modificar el esquema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT | Clave primaria. |
| `dni` | INT UNSIGNED | Foránea a `personas`. |
| `id_eleccion` | INT | Foránea a `elecciones`. |
| `fecha_registro` | DATE | Fecha en que se registró el voto. |

---

### Tablas adicionales

Toda tabla que se incorpore al sistema debe respetar los siguientes campos obligatorios:

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria interna. |
| `dni` | INT UNSIGNED | Clave de cruce. Puede no matchear con `personas` hoy. |
| `apellido` | VARCHAR(120) | Para verificación manual si el DNI no matchea. |
| `nombre` | VARCHAR(120) | Ídem. |
| `fecha_carga` | DATE | Fecha de incorporación del listado. |

**Tablas adicionales actualmente disponibles en `fiscaliz_graduados`:**
- `padronucrpba` — afiliados a la UCR Provincia de Buenos Aires (~800.000 registros). Campos: sección, apellido, nombre, género, tipo DNI, DNI.
- `padronucrcaba24` — afiliados a la UCR CABA (~160.000 registros). Campos: comuna, barrio, circuito, apellido, nombre, sexo, tipo DNI, DNI, dirección.
- `auxiliarescp24` — docentes auxiliares habilitados para votar en CP que no figuran en el padrón oficial. Se mantiene como tabla permanente e independiente.

**Tabla pendiente:**
- `sede_laboral` — creada, vacía. Se carga cuando el administrador tenga el listado tuneado.

---

## 4. Vistas principales

Las vistas son la única interfaz entre la base de datos y el PHP. El PHP hace SELECT contra las vistas y nunca consulta las tablas directamente. La exportación a Excel se construye dinámicamente desde el resultado de la vista sin modificar el código.

### `vista_padron_cd`
Perfil completo de cada habilitado para votar en CD. Cruza por DNI: `padron_cd` → `referentes_graduado` → `referentes` (×3) → `persona_partido` → `partidos` → `persona_trabajo` → `trabajos` → `sede_laboral` → `participacion_electoral` (por cada elección CD). Todos los joins son LEFT JOIN.

### `vista_padron_cp`
Ídem para CP. Incluye el campo `auxiliar`. Cruza participación para las 4 elecciones de CP: 2017, 2019, 2021, 2024.

### Agregar una elección nueva
Requiere dos operaciones sobre las vistas:
1. Agregar el LEFT JOIN a `participacion_electoral` con el nuevo `id_eleccion`.
2. Agregar la columna `CASE WHEN` correspondiente en el SELECT.

El PHP no se modifica.

### Agregar una tabla nueva
Requiere una operación sobre las vistas:
1. Agregar el LEFT JOIN a la nueva tabla por DNI.
2. Agregar las columnas a mostrar en el SELECT.

El PHP no se modifica.

---

## 5. Relaciones entre tablas

```
personas ──────────────── padron_cd               (dni)
personas ──────────────── padron_cp               (dni)
personas ──────────────── referentes_graduado     (dni)
personas ──────────────── persona_partido         (dni)
personas ──────────────── persona_trabajo         (dni)
personas ──────────────── participacion_electoral (dni)
personas ──────────────── sede_laboral            (dni, LEFT JOIN)
personas ──────────────── tablas adicionales      (dni, LEFT JOIN)

referentes ────────────── referentes_graduado     (id → referente_1/2/3)
partidos ──────────────── persona_partido         (id → id_partido)
trabajos ──────────────── persona_trabajo         (id → id_trabajo)
elecciones ────────────── participacion_electoral (id → id_eleccion)
```

---

## 6. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| `personas` | 19.709 | ✅ Migrado |
| `padron_cd` | 19.521 | ✅ Migrado |
| `padron_cp` | 4.554 | ✅ Migrado |
| `carreras` | 5 | ✅ Cargado |
| `referentes` | 270 | ✅ Migrado |
| `partidos` | 54 | ✅ Migrado |
| `trabajos` | 76 | ✅ Migrado |
| `elecciones` | 6 | ✅ Cargado |
| `referentes_graduado` | 19.709 | ✅ Migrado |
| `persona_partido` | 1.371 | ✅ Migrado |
| `persona_trabajo` | 2.150 | ✅ Migrado |
| `participacion_electoral` | 11.965 | ✅ Migrado |
| `sede_laboral` | 0 | ⏳ Pendiente listado |

**Nota:** los datos migrados son válidos para desarrollo. La validación profunda de consistencia se realiza antes de pasar a producción.

---

## 7. Lo que este esquema deja preparado para Fiscalización

- `elecciones` ya existe con el campo `activa` para identificar la elección en curso.
- `participacion_electoral` recibirá los registros del día de la elección.
- Los padrones ya están separados por tipo (cd/cp).
- Las tablas de mesas, fiscales y login de fiscales se agregarán como tablas nuevas sin modificar las existentes.

---

## 8. Lo que este esquema NO incluye todavía

- Tablas de usuarios del sistema y niveles de acceso.
- Tablas de mesas electorales y fiscales.
- Registro en tiempo real de votos (módulo Fiscalización).

---

## Resumen

El nuevo esquema centraliza la identidad de cada individuo en `personas` (DNI, apellido, nombre), mantiene los padrones CD y CP puros tal como los entrega la facultad, separa los vínculos con partido y trabajo en tablas independientes actualizables vía ABM, reemplaza el historial electoral embebido por una tabla de participación donde solo figuran los que votaron, y expone todo el dato consolidado a través de dos vistas principales que el PHP consulta directamente. Incorporar una tabla nueva o una elección nueva implica extender las vistas: el código PHP no se modifica. La base está migrada y lista para el desarrollo de la etapa Consulta Padrón.
