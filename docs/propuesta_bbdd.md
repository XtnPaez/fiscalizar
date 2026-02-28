# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar  
**Fecha:** Febrero 2026  
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
    referentes_graduado     (DNI ↔ hasta 3 referentes)
    elecciones              (catálogo de procesos electorales)
    participacion_electoral (historial: quién votó en qué elección)

TABLAS ADICIONALES (una por fuente, todas con DNI obligatorio)
    sede_laboral
    (futuras)

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
| `dni` | INT UNSIGNED | Clave primaria. Identificador único. |
| `apellido` | VARCHAR(120) | Apellido en mayúsculas. |
| `nombre` | VARCHAR(120) | Nombre/s en mayúsculas. |

---

### `padron_cd`
**Rol:** padrón oficial de Consejo Directivo tal como lo entrega la facultad. Se actualiza con cada elección sumando los nuevos habilitados. Nunca se eliminan registros. No contiene DNIs repetidos dentro de la tabla, pero puede compartir DNIs con `padron_cp`.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. |
| `dni` | INT UNSIGNED | DNI. Clave de cruce con `personas` y resto de tablas. |
| `apellido` | VARCHAR(120) | Apellido tal como figura en el padrón oficial. |
| `nombre` | VARCHAR(120) | Nombre tal como figura en el padrón oficial. |
| `sigla` | VARCHAR(12) | Sigla de la carrera tal como figura en el padrón oficial. |

**Nota:** el padrón oficial de CD publicado por la facultad contiene únicamente estos cinco campos. Todo dato adicional (referentes, partido, trabajo, sede laboral, municipio, participación electoral) se joinea por DNI desde sus respectivas tablas en las vistas.

---

### `padron_cp`
**Rol:** padrón oficial de Ciencia Política tal como lo entrega la facultad. Misma lógica acumulativa que `padron_cd`. Incluye graduados de CP y docentes auxiliares habilitados.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. |
| `dni` | INT UNSIGNED | DNI. Clave de cruce con `personas` y resto de tablas. |
| `apellido` | VARCHAR(120) | Apellido tal como figura en el padrón oficial. |
| `nombre` | VARCHAR(120) | Nombre tal como figura en el padrón oficial. |

**Nota:** el padrón oficial de CP publicado por la facultad contiene únicamente estos cuatro campos. Todo dato adicional se joinea por DNI desde sus respectivas tablas en las vistas. El campo `auxiliar` (docente auxiliar) se agrega durante el tuneo previo a la carga, ya que es información que el sistema necesita y que no publica la facultad.

---

### `carreras`
**Rol:** catálogo de las 5 carreras de la facultad. Sin cambios respecto al diseño actual, solo se migra al nuevo esquema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `descripcion` | VARCHAR(50) | Nombre completo de la carrera. |
| `sigla` | VARCHAR(5) | Sigla. Ej: CP, CS, RT, TS, CC. |

---

### `referentes`
**Rol:** catálogo de referentes políticos. Reemplaza a `responsable` con nombres descriptivos, apellido y nombre separados, y tipos de datos correctos.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `apellido` | VARCHAR(80) | Apellido del referente. |
| `nombre` | VARCHAR(80) | Nombre del referente. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 dado de baja lógica. |

**Nota:** se mantiene el registro SIN REFERENTE como entrada explícita del catálogo.

---

### `partidos`
**Rol:** catálogo de espacios políticos. Reemplaza a `partido`.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `nombre` | VARCHAR(80) | Nombre del espacio político. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 dado de baja lógica. |

---

### `trabajos`
**Rol:** catálogo de lugares de trabajo. Reemplaza a `trabajo`. Mantiene las categorías DOCENTE, NO DOCENTE y ADMINISTRATIVO como valores válidos con significado para el sistema.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `nombre` | VARCHAR(120) | Nombre del lugar de trabajo o categoría. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padrón CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padrón CP. |
| `activo` | TINYINT(1) | 1 activo, 0 dado de baja lógica. |

---

### `referentes_graduado`
**Rol:** vincula cada DNI con hasta 3 referentes. El límite de 3 es firme e histórico. Se usan tres columnas fijas en lugar de filas para simplificar las vistas y la presentación. Los campos nulos indican ausencia de referente en esa posición.

| Campo | Tipo | Descripción |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foránea a `personas`. |
| `referente_1` | INT | Referencia a `referentes`. Primer referente. NULL si no tiene. |
| `referente_2` | INT | Referencia a `referentes`. Segundo referente. NULL si no tiene. |
| `referente_3` | INT | Referencia a `referentes`. Tercer referente. NULL si no tiene. |

**Justificación:** tres columnas fijas permiten que las vistas armen la presentación con un JOIN simple por cada columna, sin lógica adicional. El PHP recibe los tres valores (o NULL) y decide si mostrarlos o mostrar "Sin referente".

---

### `elecciones`
**Rol:** catálogo de procesos electorales. Permite registrar elecciones pasadas y futuras como entidades con identidad propia, evitando que el año electoral se deduzca por las fechas de los registros de voto.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `nombre` | VARCHAR(80) | Nombre descriptivo. Ej: "Elección CD 2024". |
| `tipo` | ENUM('cd','cp') | Tipo de proceso electoral. |
| `fecha` | DATE | Fecha de la elección. |
| `activa` | TINYINT(1) | 1 si es la elección en curso. Solo una puede estar activa por tipo. |

---

### `participacion_electoral`
**Rol:** historial de participación de cada graduado en cada elección. Reemplaza los campos `voto17`, `voto19`, `voto21` embebidos en los padrones actuales. Agregar una elección nueva es insertar filas aquí, no agregar columnas al padrón.

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria. |
| `dni` | INT UNSIGNED | Referencia a `personas`. |
| `id_eleccion` | INT | Referencia a `elecciones`. |
| `voto` | TINYINT(1) | 1 si votó, 0 si no votó. |
| `fecha_registro` | DATE | Fecha en que se registró el voto. |

---

### Tablas adicionales

Toda tabla que se incorpore al sistema debe respetar los siguientes campos obligatorios como mínimo:

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Clave primaria interna. |
| `dni` | INT UNSIGNED | Clave de cruce con `personas`. Puede no matchear hoy. |
| `apellido` | VARCHAR(120) | Para verificación manual cuando el DNI no matchea. |
| `nombre` | VARCHAR(120) | Ídem. |
| `fecha_carga` | DATE | Fecha en que el listado fue incorporado a la base. |

A estos campos se suman los específicos de cada tabla. Los campos que deben mostrarse en las vistas se agregan a las vistas correspondientes cuando se incorpora la tabla.

**Tablas ya identificadas:**
> **Nota:** `comuna_municipio` no existe como tabla en este esquema. El dato de municipio/comuna proviene de un listado de afiliados a un partido que el administrador tuneará y subirá cuando corresponda. En ese momento se crea la tabla y se extienden las vistas.

- `sede_laboral` — sede laboral normalizada de cada DNI.

---

## 4. Vistas principales

Las vistas son la única interfaz entre la base de datos y el PHP. El PHP hace SELECT contra las vistas; nunca consulta las tablas directamente.

---

### `vista_padron_cd`
Perfil completo de cada habilitado para votar en CD.

Cruza: `padron_cd` → `personas` → `carreras` → `referentes_graduado` → `referentes` (×3) → `partidos` → `trabajos` → `participacion_electoral` (pivoteada por elección) → `sede_laboral` → `comuna_municipio` → (tablas adicionales futuras).

Todos los joins son por DNI. Los joins a tablas adicionales son LEFT JOIN para que la ausencia de dato no excluya al graduado del resultado.

El resultado es una fila por graduado habilitado en CD con todos sus datos disponibles en columnas. Esta vista es la fuente para la consulta en pantalla y para la exportación a Excel.

---

### `vista_padron_cp`
Ídem para el padrón CP. Misma lógica, mismas fuentes, filtra por los DNIs presentes en `padron_cp`. Incluye el campo `auxiliar` para distinguir docentes auxiliares de graduados.

---

### Vistas de participación (dentro de las vistas principales)
La participación electoral se pivotea dentro de `vista_padron_cd` y `vista_padron_cp` para mostrar una columna por elección: `voto_2017`, `voto_2019`, `voto_2021`, `voto_2024`. Cuando se agrega una elección nueva a `elecciones` y se registra su participación en `participacion_electoral`, las vistas se actualizan para incluir la nueva columna. Es la única modificación SQL necesaria ante una nueva elección.

---

## 5. Relaciones entre tablas

```
personas ──────────────── padron_cd              (dni)
personas ──────────────── padron_cp              (dni)
personas ──────────────── referentes_graduado    (dni)
personas ──────────────── participacion_electoral(dni)
personas ──────────────── sede_laboral           (dni, LEFT JOIN)
personas ──────────────── comuna_municipio       (dni, LEFT JOIN)
personas ──────────────── tablas adicionales     (dni, LEFT JOIN)

referentes ────────────── referentes_graduado    (id → referente_1/2/3)
elecciones ────────────── participacion_electoral(id → id_eleccion)
carreras ──────────────── padron_cd / padron_cp  (id → id_carrera)
partidos ──────────────── padron_cd / padron_cp  (id → id_partido)
trabajos ──────────────── padron_cd / padron_cp  (id → id_trabajo)
```

---

## 6. Lo que este esquema deja preparado para Fiscalización

El módulo de Fiscalización necesitará registrar votos en tiempo real durante el día de la elección. Ese módulo se diseñará en una etapa posterior. El esquema actual lo contempla sin requerir cambios en las tablas existentes:

- La tabla `elecciones` ya existe y tiene el campo `activa` para identificar la elección en curso.
- La tabla `participacion_electoral` recibirá los registros del día de la elección.
- Los padrones ya están separados por tipo (cd/cp).
- Las tablas de mesas, fiscales y login de fiscales se agregarán como tablas nuevas en esa etapa.

---

## 7. Lo que este esquema NO incluye todavía

- Tablas de usuarios del sistema (administradores, niveles de acceso).
- Tablas de mesas electorales y fiscales.
- Registro en tiempo real de votos (módulo Fiscalización).
- Script de migración desde la base actual.

Todo esto se aborda en etapas posteriores.

---

## Resumen

El nuevo esquema centraliza la identidad de cada individuo en `personas` (DNI, apellido, nombre), mantiene los padrones CD y CP puros tal como los entrega la facultad, reemplaza el historial electoral embebido por una tabla de participación vinculada a un catálogo de elecciones, y expone todo el dato consolidado a través de dos vistas principales que el PHP consulta directamente. Incorporar una tabla nueva implica agregarla con DNI obligatorio y extender las vistas: el código PHP no se modifica. El resultado es una base normalizada, con integridad referencial garantizada por InnoDB, preparada para crecer con nuevas fuentes de datos y para escalar al módulo de Fiscalización sin cambios estructurales.
