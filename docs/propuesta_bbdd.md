# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar
**Fecha:** Junio 2026 (actualizado)
**Etapa:** Esquema productivo — version activa

---

## 1. Principios que guian este diseno

- DNI como clave unica de cruce entre todas las tablas.
- Todo InnoDB, todo utf8mb4. Integridad referencial garantizada por el motor.
- La logica vive en las vistas. El PHP hace SELECT contra vistas predefinidas.
- Los padrones se mantienen puros. Se cargan tal como los entrega la facultad.
- Padrones acumulativos. Nunca se elimina un registro.
- Escalabilidad hacia Fiscalizacion. Incorporado sin cambios en Consulta Padron.

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

TABLAS ADICIONALES (cruce por DNI)
    st_siet_2026 / st_ucr_caba_2026 / st_ucr_pba_2024

TABLAS FISCALIZACION
    dias_eleccion / mesas / votos_dia / usuarios_fiscal / punteo

VISTAS CONSULTA PADRON
    vista_padron_cd / vista_padron_cp

VISTAS FISCALIZACION
    vista_fiscal_cd / vista_fiscal_cp / vista_fiscal_rt / vista_fiscal_cs / vista_fiscal_cc
```

---

## 3. Tablas productivas

### `personas`
Un registro por DNI. Nunca se elimina. PK: dni.

### `padron_cd`
Padron oficial CD. Acumulativo. Sigla reconstruida via st_dni_carrera en 2026.
21.745 registros.

### `padron_cp`
Padron oficial CP. Campo auxiliar marcado durante el tuneo.
4.843 registros. 175 auxiliares.

### `padron_rt`
Padron oficial RT. Solo modulo Fiscalizacion.
5.240 registros. 118 auxiliares en tabla auxiliares (id_carrera=3).

### `padron_cs`
Padron oficial CS. Solo modulo Fiscalizacion.
4.829 registros. 161 auxiliares en tabla auxiliares (id_carrera=1).

### `padron_cc`
Padron oficial CC. Agregado junio 2026. Solo modulo Fiscalizacion.
4.504 registros. 153 auxiliares en tabla auxiliares (id_carrera=5).
Los 153 auxiliares no estaban en personas — se insertaron al crear la tabla.

| Campo | Tipo |
|---|---|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT |
| dni | INT UNSIGNED FK personas |
| apellido | VARCHAR(120) |
| nombre | VARCHAR(120) |

### `auxiliares`
PK compuesta (dni, id_carrera).

| Carrera | id_carrera | Auxiliares |
|---|---|---|
| Sociologia (CS) | 1 | 161 |
| Ciencia Politica (CP) | 2 | 175 |
| Relaciones del Trabajo (RT) | 3 | 118 |
| Ciencias de la Comunicacion (CC) | 5 | 153 |
| **Total** | | **607** |

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
| 9+ | Elecciones CD/CC 2026 | cd/cc | 2026 | programada/activa |

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
| nombre | VARCHAR(30) |
| habilitado | TINYINT(1) DEFAULT 0 |

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
| password | VARCHAR(255) bcrypt |
| nivel | ENUM('superadmin','admin','mira') |
| tipo | ENUM('cd','cp','rt','cs','cc') NULL |
| activo | TINYINT(1) DEFAULT 1 |

### `punteo`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_mesa | INT FK mesas |
| numero_corte | INT — MAX+1 por mesa |
| votantes | INT DEFAULT 20 |
| faltantes | INT DEFAULT 0 |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (id_mesa, numero_corte).

---

## 5. Vistas

### Consulta Padron
vista_padron_cd y vista_padron_cp — perfil completo con historial electoral.

### Fiscalizacion
Cinco vistas con VOTO_2026 desde votos_dia via EXISTS + subquery.

| Vista | Fuente | Auxiliar |
|---|---|---|
| vista_fiscal_cd | padron_cd | No aplica — todos son graduados CD |
| vista_fiscal_cp | padron_cp | padron_cp.auxiliar (campo directo) |
| vista_fiscal_rt | padron_rt | auxiliares id_carrera=3 |
| vista_fiscal_cs | padron_cs | auxiliares id_carrera=1 |
| vista_fiscal_cc | padron_cc | auxiliares id_carrera=5 |

**Nota de collation:** vista_fiscal_cc puede generar error 1267 al filtrar por
voto_2026 en PHP. Se resuelve con COLLATE utf8mb4_unicode_ci en el WHERE.
Pendiente: recrear la vista con COLLATE explicito para eliminar el workaround.

---

## 6. Relaciones

```
personas ──── padron_cd/cp/rt/cs/cc
personas ──── auxiliares / referentes_graduado
personas ──── persona_partido/trabajo/sede/municipio
personas ──── participacion_electoral / votos_dia (via mesas)

carreras ──── auxiliares
elecciones ── participacion_electoral / dias_eleccion
dias_eleccion ── mesas
mesas ────── votos_dia / punteo
```

---

## 7. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| personas | 22.478 | ✅ Junio 2026 (+153 de CC) |
| padron_cd | 21.745 | ✅ Mayo 2026 |
| padron_cp | 4.843 | ✅ Mayo 2026 |
| padron_rt | 5.240 | ✅ Mayo 2026 |
| padron_cs | 4.829 | ✅ Mayo 2026 |
| padron_cc | 4.504 | ✅ Junio 2026 |
| auxiliares | 607 | ✅ Junio 2026 (+153 CC) |
| carreras | 7 | ✅ |
| referentes | 324 | ✅ Mayo 2026 |
| partidos | 87 | ✅ Mayo 2026 |
| trabajos | 105 | ✅ Mayo 2026 |
| sedes | 50 | ✅ |
| municipios | 84 | ✅ |
| elecciones | 9+ | ✅ Junio 2026 |
| referentes_graduado | 22.325 | ✅ Mayo 2026 + correccion junio 2026 |
| persona_partido | 1.560 | ✅ |
| persona_trabajo | 2.150 | ✅ |
| persona_sede | 19.709 | ✅ |
| persona_municipio | 19.709 | ✅ |
| participacion_electoral | 11.974 | ✅ |
| usuarios | 1 | ✅ |
| dias_eleccion | variable | ⏳ Se crea desde la interfaz |
| mesas | variable | ⏳ Se crea desde la interfaz |
| votos_dia | 0 | ⏳ Dia de la eleccion |
| usuarios_fiscal | 6+ | ✅ Superadmin + usuarios mira (cd/cp/rt/cs/cc) |
| punteo | 0 | ⏳ Dia de la eleccion |

---

## 8. Nota de collation

config/db.php de ambos modulos debe incluir:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

vista_fiscal_cc: usar COLLATE utf8mb4_unicode_ci en WHERE voto_2026 desde PHP.
