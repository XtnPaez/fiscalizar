# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar
**Fecha:** Junio 2026 (version final)
**Etapa:** Esquema productivo activo

---

## 1. Principios

- DNI como clave unica de cruce entre todas las tablas.
- Todo InnoDB, todo utf8mb4. Integridad referencial por el motor.
- La logica vive en las vistas. El PHP hace SELECT contra vistas predefinidas.
- Los padrones se mantienen puros. Sin modificaciones al cargar.
- Padrones acumulativos. Nunca se elimina un registro.

---

## 2. Mapa general de tablas

```
NUCLEO
    personas

PADRONES
    padron_cd / padron_cp / padron_rt / padron_cs / padron_cc

CATALOGOS
    carreras / referentes / partidos / trabajos / sedes / municipios

RELACIONES
    auxiliares / referentes_graduado / persona_partido / persona_trabajo
    persona_sede / persona_municipio / elecciones / participacion_electoral

AUTENTICACION CONSULTA PADRON
    usuarios

TABLAS ADICIONALES
    st_siet_2026 / st_ucr_caba_2026 / st_ucr_pba_2024

TABLAS FISCALIZACION
    dias_eleccion / mesas / votos_dia / usuarios_fiscal / punteo

VISTAS CONSULTA PADRON
    vista_padron_cd / vista_padron_cp

VISTAS FISCALIZACION
    vista_fiscal_cd / vista_fiscal_cp / vista_fiscal_rt / vista_fiscal_cs / vista_fiscal_cc
```

---

## 3. Tablas productivas principales

### `personas`
22.478 registros. Un registro por DNI. Nunca se elimina.

### Padrones

| Tabla | Registros | Auxiliares | Notas |
|---|---|---|---|
| padron_cd | 21.745 | — | Sigla reconstruida 2026 |
| padron_cp | 4.843 | 175 (campo directo) | |
| padron_rt | 5.240 | 118 (auxiliares id_carrera=3) | |
| padron_cs | 4.829 | 161 (auxiliares id_carrera=1) | |
| padron_cc | 4.504 | 153 (auxiliares id_carrera=5) | Agregado junio 2026 |

### `auxiliares`
607 registros totales. PK compuesta (dni, id_carrera).

### `carreras`

| id | sigla | descripcion |
|---|---|---|
| 1 | CS | Sociologia |
| 2 | CP | Ciencia Politica |
| 3 | RT | Relaciones del Trabajo |
| 4 | TS | Trabajo Social |
| 5 | CC | Ciencias de la Comunicacion |
| 98 | NS | No identificada |
| 99 | SD | Sin dato |

### `elecciones`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| nombre | VARCHAR(80) |
| tipo | ENUM('cd','cp','rt','cs','cc') |
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
| 9+ | Elecciones 2026 | cd/cc/... | 2026 | variable |

---

## 4. Tablas de Fiscalizacion

### `dias_eleccion`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_eleccion | INT FK elecciones |
| nombre | VARCHAR(30) |
| habilitado | TINYINT(1) DEFAULT 0 |

La habilitacion a nivel dia controla que mesas aparecen en el login del fiscal.

### `mesas`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| nombre | VARCHAR(60) |
| tipo | ENUM('cd','cp','rt','cs','cc') |
| id_dia | INT FK dias_eleccion |
| password | VARCHAR(255) bcrypt |
| en_uso | TINYINT(1) DEFAULT 0 |
| activa | TINYINT(1) DEFAULT 1 |

`activa` = habilitacion individual de la mesa independiente del dia.
`habilitada` = campo deprecado, ignorado en toda la logica.

El login filtra: d.habilitado=1 AND m.activa=1 AND m.en_uso=0 AND e.estado='activa'.
El dashboard muestra: d.habilitado=1 AND m.activa=1 AND e.estado='activa'.
El conteo de votos en dashboard: todos los dias de la eleccion activa (sin filtro de dia).

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
| password | VARCHAR(255) bcrypt |
| nivel | ENUM('superadmin','admin','mira') |
| tipo | ENUM('cd','cp','rt','cs','cc') NULL |
| activo | TINYINT(1) DEFAULT 1 |

### `punteo`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_mesa | INT FK mesas |
| numero_corte | INT |
| votantes | INT DEFAULT 20 |
| faltantes | INT DEFAULT 0 |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (id_mesa, numero_corte). Numero asignado automaticamente (MAX+1).

---

## 5. Vistas

### Consulta Padron
vista_padron_cd y vista_padron_cp — perfil completo con historial electoral.
No modificadas por Fiscalizacion.

### Fiscalizacion

| Vista | Fuente | Auxiliar | VOTO_2026 |
|---|---|---|---|
| vista_fiscal_cd | padron_cd | No aplica | EXISTS sobre mesas activas tipo cd |
| vista_fiscal_cp | padron_cp | padron_cp.auxiliar | EXISTS sobre mesas activas tipo cp |
| vista_fiscal_rt | padron_rt | auxiliares id_carrera=3 | EXISTS sobre mesas activas tipo rt |
| vista_fiscal_cs | padron_cs | auxiliares id_carrera=1 | EXISTS sobre mesas activas tipo cs |
| vista_fiscal_cc | padron_cc | auxiliares id_carrera=5 | EXISTS sobre mesas activas tipo cc |

Todas filtran por m.activa=1 en el subquery de votos.
vista_fiscal_cc: workaround COLLATE utf8mb4_unicode_ci en PHP al filtrar voto_2026.

---

## 6. Relaciones

```
personas ──── padron_cd/cp/rt/cs/cc
personas ──── auxiliares / referentes_graduado
personas ──── persona_partido/trabajo/sede/municipio
personas ──── participacion_electoral
personas ──── votos_dia (via mesas -> dias_eleccion -> elecciones)

carreras ──── auxiliares
elecciones ── participacion_electoral / dias_eleccion
dias_eleccion ── mesas
mesas ────── votos_dia / punteo
```

---

## 7. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| personas | 22.478 | ✅ Junio 2026 |
| padron_cd | 21.745 | ✅ Mayo 2026 |
| padron_cp | 4.843 | ✅ Mayo 2026 |
| padron_rt | 5.240 | ✅ Mayo 2026 |
| padron_cs | 4.829 | ✅ Mayo 2026 |
| padron_cc | 4.504 | ✅ Junio 2026 |
| auxiliares | 607 | ✅ Junio 2026 |
| carreras | 7 | ✅ |
| referentes | 324 | ✅ Mayo 2026 |
| partidos | 87 | ✅ Mayo 2026 |
| trabajos | 105 | ✅ Mayo 2026 |
| sedes | 50 | ✅ |
| municipios | 84 | ✅ |
| elecciones | 9+ | ✅ Junio 2026 |
| referentes_graduado | 22.325 | ✅ Correccion junio 2026 |
| persona_partido | 1.560 | ✅ |
| persona_trabajo | 2.150 | ✅ |
| persona_sede | 19.709 | ✅ |
| persona_municipio | 19.709 | ✅ |
| participacion_electoral | 11.974 | ✅ |
| usuarios | 1 | ✅ |
| dias_eleccion | variable | ⏳ Se crea desde la interfaz |
| mesas | variable | ⏳ Se crea desde la interfaz |
| votos_dia | 0 | ⏳ Dia de la eleccion |
| usuarios_fiscal | variable | ✅ Superadmin + mira creados |
| punteo | 0 | ⏳ Dia de la eleccion |

---

## 8. Nota de collation

config/db.php debe incluir:
```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

vista_fiscal_cc: error 1267 al filtrar por voto_2026 desde PHP.
Workaround activo en consulta.php: COLLATE utf8mb4_unicode_ci en el WHERE.
Pendiente: recrear la vista con COLLATE explicito en todos los campos calculados.
