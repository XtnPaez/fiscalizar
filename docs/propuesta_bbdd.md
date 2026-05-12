# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar
**Fecha:** Junio 2026 (actualizado)
**Etapa:** Esquema productivo — version activa

---

## 1. Principios que guian este diseno

- **DNI como clave unica de cruce** entre todas las tablas.
- **Todo InnoDB, todo utf8mb4:** integridad referencial garantizada por el motor, no por el codigo.
- **La logica vive en las vistas:** el PHP hace SELECT contra vistas predefinidas y presenta lo que encuentra. No decide que tablas cruzar ni que campos mostrar.
- **Los padrones se mantienen puros:** se cargan tal como los entrega la facultad, con todos sus campos originales.
- **Padrones acumulativos:** nunca se elimina un registro. Solo se suman nuevos habilitados con cada eleccion.
- **Todas las tablas se administran igual:** el administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.
- **Escalabilidad hacia Fiscalizacion:** el esquema incorporo el modulo electoral sin cambios estructurales en las tablas de Consulta Padron.

---

## 2. Mapa general de tablas

```
NUCLEO
    personas

PADRONES (puros, tal como los entrega la facultad)
    padron_cd       — graduados habilitados para CD (todas las carreras)
    padron_cp       — graduados y auxiliares habilitados para CP
    padron_rt       — graduados y auxiliares habilitados para RT
    padron_cs       — graduados y auxiliares habilitados para CS (Sociologia)

CATALOGOS
    carreras
    referentes
    partidos
    trabajos
    sedes
    municipios

RELACIONES
    auxiliares              (DNI <-> carrera donde es auxiliar, many-to-many)
    referentes_graduado     (DNI <-> hasta 3 referentes, limite firme e historico)
    persona_partido         (DNI <-> espacio politico)
    persona_trabajo         (DNI <-> lugar de trabajo)
    persona_sede            (DNI <-> sede laboral)
    persona_municipio       (DNI <-> municipio o comuna)
    elecciones              (catalogo de procesos electorales: cd, cp, rt, cs)
    participacion_electoral (historial: solo se registran los que votaron)

AUTENTICACION
    usuarios                (usuarios del modulo Consulta Padron)

TABLAS ADICIONALES (cruce por DNI via LEFT JOIN)
    st_siet_2026
    st_ucr_caba_2026
    st_ucr_pba_2024

TABLAS STAGING (prefijo st_, fuente de migraciones y actualizaciones)
    st_carreras
    st_referentes
    st_partidos
    st_trabajo
    st_padron_cd_datos
    st_padron_cp_datos
    st_auxiliares_cp
    st_votos_cd_24
    st_votos_cp_24
    st_padron_cd_2026
    st_padron_cp_2026
    st_padron_rt_2026
    st_padron_cs_2026
    st_padron_ts_2026           (TS queda solo en staging, sin tabla productiva)
    st_padron_cc_2026           (CC queda solo en staging, sin tabla productiva)
    st_dni_carrera              (auxiliar: una sigla por DNI para reconstruir padron_cd.sigla)
    st_padron_profesores_cp_2026
    st_actualizacion_referentes_2026
    st_bajas_padron_cd_2026
    st_bajas_padron_cd_2026
    st_padron_cd_2024           (backup pre-actualizacion)
    st_padron_cp_2024           (backup pre-actualizacion)
    st_padron_cp_pre_auxiliares_2026 (backup pre-marcado de auxiliares)
    st_persona_partido_pre_actualizacion_2026 (backup pre-actualizacion)

TABLAS FISCALIZACION (modulo electoral — activo desde junio 2026)
    dias_eleccion
    mesas
    votos_dia
    usuarios_fiscal
    punteo

VISTAS CONSULTA PADRON
    vista_padron_cd
    vista_padron_cp

VISTAS FISCALIZACION
    vista_fiscal_cd
    vista_fiscal_cp
    vista_fiscal_rt
    vista_fiscal_cs
```

---

## 3. Descripcion de cada tabla productiva

---

### `personas`
**Rol:** tabla nucleo del esquema. Un registro unico por DNI. Punto de joineo de todas las tablas. Nunca se elimina un registro.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria. |
| `apellido` | VARCHAR(120) | Mayusculas, sin tildes, con N. |
| `nombre` | VARCHAR(120) | Mayusculas, sin tildes, con N. |

---

### `padron_cd`
**Rol:** padron oficial de Consejo Directivo. Acumulativo. A partir de 2026 sin sigla de carrera en el original — se reconstruye via st_dni_carrera.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | FK a personas. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `sigla` | VARCHAR(12) | Sigla de carrera. NS para los 13 no identificados. |

---

### `padron_cp`
**Rol:** padron oficial de Ciencia Politica. Incluye graduados y auxiliares. Campo auxiliar marcado durante el tuneo.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | FK a personas. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `auxiliar` | TINYINT(1) | 1 = docente auxiliar, 0 = graduado. |

---

### `padron_rt`
**Rol:** padron oficial de Relaciones del Trabajo. Solo modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | FK a personas. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |

---

### `padron_cs`
**Rol:** padron oficial de Sociologia. Solo modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | FK a personas. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |

---

### `auxiliares`
**Rol:** registra que DNIs son auxiliares docentes y en que carrera. PK compuesta.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | PK compuesta. FK a personas. |
| `id_carrera` | INT | PK compuesta. FK a carreras. |

| Carrera | id_carrera | Auxiliares |
|---|---|---|
| Sociologia (CS) | 1 | 161 |
| Ciencia Politica (CP) | 2 | 175 |
| Relaciones del Trabajo (RT) | 3 | 118 |
| **Total** | | **454** |

---

### `carreras`
**Rol:** catalogo cerrado de carreras. Sin AUTO_INCREMENT.

| id | descripcion | sigla |
|---|---|---|
| 1 | Sociologia | CS |
| 2 | Ciencia Politica | CP |
| 3 | Relaciones del Trabajo | RT |
| 4 | Trabajo Social | TS |
| 5 | Ciencias de la Comunicacion | CC |
| 98 | No identificada | NS |
| 99 | Sin dato | SD |

---

### `referentes`
**Rol:** catalogo de referentes politicos. id=250 reservado para SIN REFERENTE.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `apellido` | VARCHAR(80) | Apellido del referente. |
| `nombre` | VARCHAR(80) | Nombre del referente. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `partidos`
**Rol:** catalogo de espacios politicos. Baja logica via activo.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(80) | Nombre del espacio politico. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `trabajos`
**Rol:** catalogo de lugares de trabajo. Baja logica via activo.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre del lugar de trabajo. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `sedes`
**Rol:** catalogo de sedes laborales. 50 valores. id=1 reservado para SIN DATO.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre de la sede. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `municipios`
**Rol:** catalogo de municipios y comunas. 84 valores. id=1 reservado para SIN DATO.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre del municipio o comuna. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `referentes_graduado`
**Rol:** hasta 3 referentes por DNI. Posiciones vacias apuntan a id=250.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | PK y FK a personas. |
| `referente_1` | INT | FK a referentes. 250 si no tiene. |
| `referente_2` | INT | FK a referentes. 250 si no tiene. |
| `referente_3` | INT | FK a referentes. 250 si no tiene. |

---

### `persona_partido` / `persona_trabajo` / `persona_sede` / `persona_municipio`
**Rol:** vinculo dni -> catalogo. Uno por persona.

---

### `elecciones`
**Rol:** catalogo de procesos electorales. El campo activa fue reemplazado por estado ENUM en junio 2026.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(80) | Ej: Eleccion CD 2024. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de proceso electoral. |
| `anio` | YEAR | Año de la eleccion. |
| `estado` | ENUM('programada','activa','cerrada') | Estado del ciclo de vida. |

| id | nombre | tipo | anio | estado |
|---|---|---|---|---|
| 1 | Eleccion CP 2017 | cp | 2017 | cerrada |
| 2 | Eleccion CP 2019 | cp | 2019 | cerrada |
| 3 | Eleccion CD 2021 | cd | 2021 | cerrada |
| 4 | Eleccion CP 2021 | cp | 2021 | cerrada |
| 5 | Eleccion CD 2024 | cd | 2024 | cerrada |
| 6 | Eleccion CP 2024 | cp | 2024 | cerrada |
| 7 | Eleccion RT 2026 | rt | 2026 | programada |
| 8 | Eleccion CS 2026 | cs | 2026 | programada |

Nuevas elecciones se crean desde la interfaz (ABM Elecciones — superadmin).

---

### `participacion_electoral`
**Rol:** historial de participacion. Solo se registran los que votaron.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | FK a personas. |
| `id_eleccion` | INT | FK a elecciones. |
| `fecha_registro` | DATE | Fecha en que se registro el voto. |

---

### `usuarios`
**Rol:** usuarios del modulo Consulta Padron. Independiente de usuarios_fiscal.

---

### `dias_eleccion` (Fiscalizacion)
**Rol:** dias de cada eleccion. La habilitacion a nivel dia controla el acceso de los fiscales.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_eleccion` | INT | FK a elecciones. |
| `nombre` | VARCHAR(30) | Ej: Lunes, Martes. |
| `habilitado` | TINYINT(1) | 1 = mesas del dia visibles en el login. |

---

### `mesas` (Fiscalizacion)
**Rol:** mesas electorales. El tipo se hereda de la eleccion via dias_eleccion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(60) | Ej: CD-LU-M1. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de padron que atiende. |
| `id_dia` | INT | FK a dias_eleccion. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `en_uso` | TINYINT(1) | 1 = sesion activa. |
| `activa` | TINYINT(1) | 1 = puede recibir votos. |

---

### `votos_dia` (Fiscalizacion)
**Rol:** registro en tiempo real. Se migra a participacion_electoral al cerrar la eleccion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | DNI del votante. |
| `id_mesa` | INT | FK a mesas. |
| `tipo_voto` | ENUM('regular','observado') | Default regular. |
| `timestamp` | DATETIME | Default CURRENT_TIMESTAMP. |

UNIQUE KEY (dni, id_mesa). La eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones.

---

### `usuarios_fiscal` (Fiscalizacion)
**Rol:** usuarios admin y superadmin del modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('superadmin','admin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `punteo` (Fiscalizacion)
**Rol:** punteo de nuestra lista por corte y por mesa durante el dia de la eleccion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_mesa` | INT | FK a mesas. |
| `numero_corte` | INT | Numero de corte. Asignado automaticamente (MAX+1 por mesa). |
| `votantes` | INT | Votantes en este corte. Normalmente 20, puede variar. |
| `faltantes` | INT | Boletas de nuestra lista que faltaron = votos estimados. |
| `timestamp` | DATETIME | Default CURRENT_TIMESTAMP. |

UNIQUE KEY (id_mesa, numero_corte). Acumulados calculados siempre, nunca almacenados.

---

## 4. Vistas principales

### Vistas de Consulta Padron

**`vista_padron_cd`** y **`vista_padron_cp`** — perfil completo de cada habilitado
con referentes, partido, trabajo, sede, municipio y participacion electoral historica.
Fuente exclusiva del modulo Consulta Padron.

### Vistas de Fiscalizacion

**`vista_fiscal_cd`**, **`vista_fiscal_cp`**, **`vista_fiscal_rt`**, **`vista_fiscal_cs`** —
perfil del padron activo con VOTO_2026 desde votos_dia en lugar de participacion_electoral.
Usan EXISTS + subquery sobre mesas de la eleccion activa del tipo correspondiente
para evitar duplicados cuando una eleccion tiene mas de un dia.

CD incluye CARRERA, no incluye AUXILIAR.
CP/RT/CS incluyen AUXILIAR (desde padron_cp.auxiliar para CP, desde tabla auxiliares para RT y CS).

---

## 5. Relaciones entre tablas

```
personas ──────────────── padron_cd               (dni)
personas ──────────────── padron_cp               (dni)
personas ──────────────── padron_rt               (dni)
personas ──────────────── padron_cs               (dni)
personas ──────────────── auxiliares              (dni)
personas ──────────────── referentes_graduado     (dni)
personas ──────────────── persona_partido         (dni)
personas ──────────────── persona_trabajo         (dni)
personas ──────────────── persona_sede            (dni)
personas ──────────────── persona_municipio       (dni)
personas ──────────────── participacion_electoral (dni)
personas ──────────────── votos_dia               (dni, via mesas)

carreras ──────────────── auxiliares              (id -> id_carrera)
referentes ────────────── referentes_graduado     (id -> referente_1/2/3)
partidos ──────────────── persona_partido         (id -> id_partido)
trabajos ──────────────── persona_trabajo         (id -> id_trabajo)
sedes ─────────────────── persona_sede            (id -> id_sede)
municipios ────────────── persona_municipio       (id -> id_municipio)
elecciones ────────────── participacion_electoral (id -> id_eleccion)
elecciones ────────────── dias_eleccion           (id -> id_eleccion)
dias_eleccion ─────────── mesas                   (id -> id_dia)
mesas ─────────────────── votos_dia               (id -> id_mesa)
mesas ─────────────────── punteo                  (id -> id_mesa)
```

---

## 6. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| `personas` | 22.325 | ✅ Actualizado mayo 2026 |
| `padron_cd` | 21.745 | ✅ Actualizado mayo 2026 |
| `padron_cp` | 4.843 | ✅ Actualizado mayo 2026 |
| `padron_rt` | 5.240 | ✅ Cargado mayo 2026 |
| `padron_cs` | 4.829 | ✅ Cargado mayo 2026 |
| `auxiliares` | 454 | ✅ Cargado mayo 2026 |
| `carreras` | 6 | ✅ Cargado |
| `referentes` | 324 | ✅ Actualizado mayo 2026 |
| `partidos` | 87 | ✅ Actualizado mayo 2026 |
| `trabajos` | 105 | ✅ Actualizado mayo 2026 |
| `sedes` | 50 | ✅ Cargado |
| `municipios` | 84 | ✅ Cargado |
| `elecciones` | 8 | ✅ Actualizado junio 2026 (campo estado) |
| `referentes_graduado` | 22.325 | ✅ Actualizado mayo 2026 |
| `persona_partido` | 1.560 | ✅ Actualizado mayo 2026 |
| `persona_trabajo` | 2.150 | ✅ Migrado |
| `persona_sede` | 19.709 | ✅ Cargado |
| `persona_municipio` | 19.709 | ✅ Cargado |
| `participacion_electoral` | 11.974 | ✅ Migrado |
| `usuarios` | 1 | ✅ Superadmin creado |
| `dias_eleccion` | 0 | ⏳ Se crea desde la interfaz |
| `mesas` | 0 | ⏳ Se crea desde la interfaz |
| `votos_dia` | 0 | ⏳ Se puebla el dia de la eleccion |
| `usuarios_fiscal` | 1 | ✅ Superadmin creado |
| `punteo` | 0 | ⏳ Se puebla el dia de la eleccion |

---

## 7. Nota de collation

Las tablas productivas usan utf8mb4_spanish_ci. Las tablas staging usan utf8mb4_unicode_ci.
Para evitar el error 1271 al hacer UNION entre vistas de distintas collations, la conexion
PDO incluye en config/db.php:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Esta linea es obligatoria en ambos modulos (Consulta Padron y Fiscalizacion).

---

## Resumen

El esquema centraliza la identidad en personas, mantiene cuatro padrones puros,
identifica auxiliares por carrera, separa vinculos en tablas independientes y expone
todo a traves de vistas. El modulo Fiscalizacion agrego cinco tablas propias
(dias_eleccion, mesas, votos_dia, usuarios_fiscal, punteo) y cuatro vistas nuevas
sin modificar las tablas ni vistas de Consulta Padron.
