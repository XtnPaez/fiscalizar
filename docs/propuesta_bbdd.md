# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar
**Fecha:** Junio 2026 (actualizado)
**Etapa:** Esquema productivo — version activa

---

## 1. Principios que guian este diseno

- **DNI como clave unica de cruce** entre todas las tablas.
- **Todo InnoDB, todo utf8mb4:** integridad referencial garantizada por el motor.
- **La logica vive en las vistas:** el PHP hace SELECT contra vistas predefinidas.
- **Los padrones se mantienen puros:** se cargan tal como los entrega la facultad.
- **Padrones acumulativos:** nunca se elimina un registro.
- **Escalabilidad hacia Fiscalizacion:** incorporado sin cambios en Consulta Padron.

---

## 2. Mapa general de tablas

```
NUCLEO
    personas

PADRONES
    padron_cd / padron_cp / padron_rt / padron_cs

CATALOGOS
    carreras / referentes / partidos / trabajos / sedes / municipios

RELACIONES
    auxiliares / referentes_graduado / persona_partido / persona_trabajo
    persona_sede / persona_municipio / elecciones / participacion_electoral

AUTENTICACION CONSULTA PADRON
    usuarios

TABLAS ADICIONALES (cruce por DNI)
    st_siet_2026 / st_ucr_caba_2026 / st_ucr_pba_2024

TABLAS STAGING (prefijo st_)
    [ver migracion.md para listado completo]

TABLAS FISCALIZACION
    dias_eleccion / mesas / votos_dia / usuarios_fiscal / punteo

VISTAS CONSULTA PADRON
    vista_padron_cd / vista_padron_cp

VISTAS FISCALIZACION
    vista_fiscal_cd / vista_fiscal_cp / vista_fiscal_rt / vista_fiscal_cs
```

---

## 3. Descripcion de tablas productivas

### `personas`
Un registro por DNI. Nunca se elimina. PK: dni.

### `padron_cd`
Padron oficial CD. Acumulativo. Sigla reconstruida via st_dni_carrera en 2026.

| Campo | Tipo |
|---|---|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT |
| dni | INT UNSIGNED FK personas |
| apellido | VARCHAR(120) |
| nombre | VARCHAR(120) |
| sigla | VARCHAR(12) NULL — NS para los 13 no identificados |

### `padron_cp`
Padron oficial CP. Campo auxiliar marcado durante el tuneo.

| Campo | Tipo |
|---|---|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT |
| dni | INT UNSIGNED FK personas |
| apellido | VARCHAR(120) |
| nombre | VARCHAR(120) |
| auxiliar | TINYINT(1) — 1=auxiliar, 0=graduado |

### `padron_rt` / `padron_cs`
Padrones oficiales RT y CS. Solo modulo Fiscalizacion. Sin campo auxiliar en tabla — se determina via tabla auxiliares.

### `auxiliares`
PK compuesta (dni, id_carrera). 454 registros: 175 CP, 161 CS, 118 RT.

### `carreras`
Sin AUTO_INCREMENT. ids con significado propio.

| id | sigla | descripcion |
|---|---|---|
| 1 | CS | Sociologia |
| 2 | CP | Ciencia Politica |
| 3 | RT | Relaciones del Trabajo |
| 4 | TS | Trabajo Social |
| 5 | CC | Ciencias de la Comunicacion |
| 98 | NS | No identificada |
| 99 | SD | Sin dato |

### `referentes`
id=250 reservado para SIN REFERENTE. Baja logica via activo.

### `partidos` / `trabajos` / `sedes` / `municipios`
Catalogos con baja logica. sedes y municipios: id=1 reservado para SIN DATO.

### `referentes_graduado`
Hasta 3 referentes por DNI. Posiciones vacias apuntan a id=250.

### `persona_partido` / `persona_trabajo` / `persona_sede` / `persona_municipio`
Vinculo dni -> catalogo. Uno por persona.

### `elecciones`
Campo activa reemplazado por estado ENUM en junio 2026.

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| nombre | VARCHAR(80) |
| tipo | ENUM('cd','cp','rt','cs') |
| anio | YEAR |
| estado | ENUM('programada','activa','cerrada') DEFAULT 'programada' |

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

Nuevas elecciones se crean desde la interfaz (ABM Elecciones).

### `participacion_electoral`
Historial de participacion. Solo se registran los que votaron.

### `usuarios`
Usuarios de Consulta Padron. Independiente de usuarios_fiscal.

---

## 4. Tablas de Fiscalizacion

### `dias_eleccion`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_eleccion | INT FK elecciones |
| nombre | VARCHAR(30) — Ej: Lunes, Martes |
| habilitado | TINYINT(1) DEFAULT 0 |

### `mesas`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| nombre | VARCHAR(60) — Ej: CD-LU-M1 |
| tipo | ENUM('cd','cp','rt','cs') |
| id_dia | INT FK dias_eleccion |
| password | VARCHAR(255) — bcrypt |
| en_uso | TINYINT(1) DEFAULT 0 |
| activa | TINYINT(1) DEFAULT 1 |

Nota: campo habilitada existe pero deprecado.

### `votos_dia`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| dni | INT UNSIGNED |
| id_mesa | INT FK mesas |
| tipo_voto | ENUM('regular','observado') DEFAULT 'regular' |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (dni, id_mesa).
La eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones.

### `usuarios_fiscal`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| usuario | VARCHAR(60) UNIQUE |
| password | VARCHAR(255) — bcrypt |
| nivel | ENUM('superadmin','admin','mira') |
| tipo | ENUM('cd','cp','rt','cs') NULL — solo para nivel mira |
| activo | TINYINT(1) DEFAULT 1 |

El campo tipo define que padron puede ver el usuario mira al loguearse.
Para superadmin y admin tipo es NULL.

### `punteo`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_mesa | INT FK mesas |
| numero_corte | INT — MAX+1 por mesa, asignado automaticamente |
| votantes | INT DEFAULT 20 — puede variar |
| faltantes | INT DEFAULT 0 — boletas de nuestra lista que faltaron |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (id_mesa, numero_corte).
Acumulados calculados siempre, nunca almacenados.

---

## 5. Vistas

### Vistas de Consulta Padron

**vista_padron_cd** y **vista_padron_cp** — perfil completo con referentes,
partido, trabajo, sede, municipio y participacion electoral historica.

### Vistas de Fiscalizacion

**vista_fiscal_cd/cp/rt/cs** — perfil del padron activo con VOTO_2026 desde
votos_dia. Usan EXISTS + subquery sobre mesas de la eleccion activa del tipo
correspondiente para evitar duplicados por multiples dias.

CD: incluye CARRERA, sin AUXILIAR.
CP: AUXILIAR desde padron_cp.auxiliar.
RT: AUXILIAR desde auxiliares id_carrera=3.
CS: AUXILIAR desde auxiliares id_carrera=1.

---

## 6. Relaciones

```
personas ──── padron_cd/cp/rt/cs / auxiliares / referentes_graduado
personas ──── persona_partido/trabajo/sede/municipio
personas ──── participacion_electoral / votos_dia (via mesas)

carreras ──── auxiliares
referentes ── referentes_graduado
partidos ───── persona_partido
trabajos ───── persona_trabajo
sedes ───────── persona_sede
municipios ──── persona_municipio
elecciones ──── participacion_electoral / dias_eleccion
dias_eleccion ── mesas
mesas ────────── votos_dia / punteo
```

---

## 7. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| personas | 22.325 | ✅ Mayo 2026 |
| padron_cd | 21.745 | ✅ Mayo 2026 |
| padron_cp | 4.843 | ✅ Mayo 2026 |
| padron_rt | 5.240 | ✅ Mayo 2026 |
| padron_cs | 4.829 | ✅ Mayo 2026 |
| auxiliares | 454 | ✅ Mayo 2026 |
| carreras | 6 | ✅ |
| referentes | 324 | ✅ Mayo 2026 |
| partidos | 87 | ✅ Mayo 2026 |
| trabajos | 105 | ✅ Mayo 2026 |
| sedes | 50 | ✅ |
| municipios | 84 | ✅ |
| elecciones | 8 | ✅ Junio 2026 (campo estado) |
| referentes_graduado | 22.325 | ✅ Mayo 2026 + correccion junio 2026 |
| persona_partido | 1.560 | ✅ Mayo 2026 |
| persona_trabajo | 2.150 | ✅ Migrado |
| persona_sede | 19.709 | ✅ |
| persona_municipio | 19.709 | ✅ |
| participacion_electoral | 11.974 | ✅ Migrado |
| usuarios | 1 | ✅ |
| dias_eleccion | 0 | ⏳ Se crea desde la interfaz |
| mesas | 0 | ⏳ Se crea desde la interfaz |
| votos_dia | 0 | ⏳ Dia de la eleccion |
| usuarios_fiscal | 5+ | ✅ Superadmin + usuarios mira creados |
| punteo | 0 | ⏳ Dia de la eleccion |

---

## 8. Nota de collation

Las tablas productivas usan utf8mb4_spanish_ci. Las staging usan utf8mb4_unicode_ci.
config/db.php de ambos modulos debe incluir:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

---

## Resumen

El esquema centraliza la identidad en personas, mantiene cuatro padrones puros,
identifica auxiliares por carrera, separa vinculos en tablas independientes y expone
todo a traves de vistas. Fiscalizacion agrego cinco tablas propias y cuatro vistas
nuevas sin modificar Consulta Padron. El nivel mira en usuarios_fiscal permite
acceso de solo lectura a un padron especifico sin exponer funcionalidades de administracion.
