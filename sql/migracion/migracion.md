# Log de migracion de datos

**Proyecto:** Fiscalizar
**Actualizado:** Junio 2026

---

## Contexto general

| Proceso | Fecha | Descripcion |
|---|---|---|
| Migracion inicial | Febrero 2026 | Descartada por inconsistencias. |
| Segunda migracion | Marzo 2026 | Base actual. |
| Actualizacion padrones y vinculos | Mayo 2026 | Padrones 2026, nuevas tablas, catalogos. |
| Cambios estructurales Fiscalizacion | Junio 2026 | Nuevas tablas y vistas para el modulo electoral. |
| Nivel mira y correccion referentes | Junio 2026 | Nuevo rol de acceso y correccion de migracion. |

---

## Segunda migracion — Marzo 2026

### Resultados

| Tabla | Registros |
|---|---|
| personas | 19.709 |
| padron_cd | 19.521 |
| padron_cp | 4.554 |
| referentes | 269 |
| partidos | 53 |
| trabajos | 75 |
| referentes_graduado | 19.709 |
| persona_partido | 1.371 |
| persona_trabajo | 2.150 |
| participacion_electoral | 11.974 |

### Problemas resueltos

- Conflicto de collation entre productivas (utf8mb4_spanish_ci) y staging (utf8mb4_unicode_ci). Resuelto con SET NAMES en db.php.
- UNION en INSERT no funciona en phpMyAdmin. Dos INSERT separados para referentes_graduado.
- SIN ESPACIO POLITICO incluido por error en persona_partido. DELETE y reinsert con filtro.
- DNIs duplicados en st_votos_cd_24. Resuelto con DISTINCT.

---

## Actualizacion de catalogos y vistas — Mayo 2026

### Nuevas tablas

sedes (50 valores), municipios (84 valores), persona_sede (19.709 filas), persona_municipio (19.709 filas).

### Altas en catalogos

| Catalogo | Altas | Total |
|---|---|---|
| referentes | 55 | 324 |
| partidos | 34 | 87 |
| trabajos | 30 | 105 |

### Actualizacion de padrones 2026

| Tabla | Registros |
|---|---|
| personas | 22.325 |
| padron_cd | 21.745 |
| padron_cp | 4.843 |
| padron_rt | 5.240 |
| padron_cs | 4.829 |
| auxiliares | 454 |

**Reconstruccion de sigla en padron_cd** — el padron 2026 no incluye sigla de carrera.
Se reconstruyo via st_dni_carrera cruzando cinco padrones con prioridad CP > CS > RT > TS > CC.

| Fuente | DNIs |
|---|---|
| Padrones de carrera 2026 | 21.394 |
| Padron CD 2024 | 335 |
| Correccion manual | 3 |
| NS (no identificados) | 13 |
| Total | 21.745 |

Se agrego id=98 (NS) al catalogo carreras.

---

## Cambios estructurales para Fiscalizacion — Junio 2026

Script: fiscaliz_estructura_v2.sql

### Tabla elecciones

Campo activa TINYINT(1) reemplazado por estado ENUM('programada','activa','cerrada').
- Elecciones 1-6: estado = 'cerrada'
- Elecciones 7-8 (RT 2026, CS 2026): estado = 'programada'

### Nuevas tablas

**dias_eleccion** — nivel intermedio entre elecciones y mesas.

**mesas** (modificada) — se agrego id_dia FK a dias_eleccion.
Se elimino id_eleccion (FK fk_mesas_elecciones + columna).
El campo habilitada quedo deprecado.

**votos_dia** (modificada) — se elimino id_eleccion.
Se elimino UNIQUE KEY uk_voto_dni_eleccion e indice idx_id_eleccion.
Se creo UNIQUE KEY (dni, id_mesa).

**usuarios_fiscal** — usuarios admin y superadmin de Fiscalizacion.

**punteo** — punteo de nuestra lista por corte y por mesa.

### Nuevas vistas

vista_fiscal_cd, vista_fiscal_cp, vista_fiscal_rt, vista_fiscal_cs.
Usan EXISTS + subquery para evitar duplicados por multiples dias.

### Problemas resueltos

- FK fk_mesas_elecciones requirio DROP FOREIGN KEY antes del DROP COLUMN.
- UNIQUE KEY uk_voto_dni_eleccion e indice idx_id_eleccion requirieron eliminarse antes del DROP COLUMN de votos_dia.
- Mesas de prueba eliminadas con reset_total.sql.

### Verificacion de integridad

DNIs de padron_rt y padron_cs sin par en personas: 0 en ambos casos.

Consistencia votos 2024:
- CD: 3.805 en participacion_electoral, 3.749 en vista. 56 diferencia = dados de baja en 2026, todos confirmados en st_bajas_padron_cd_2026.
- CP: 1.394 en participacion_electoral, 1.359 en vista. 35 diferencia = dados de baja en 2026, todos confirmados en st_bajas_padron_cp_2026.

---

## Correccion de referentes mal migrados — Junio 2026

Script: correccion_referentes.sql

### Problema identificado

Durante la segunda migracion, 14 referentes no se cargaron correctamente en
referentes_graduado. El problema: en staging los id_responsable usan id_origen,
pero al migrar se copio el id_origen directamente en lugar del id productivo.
Como id_origen != id_productivo para estos casos, los referentes quedaron en 250
(SIN REFERENTE).

Se identifico el problema al consultar referidos de ESLAIMAN JUAN (id_origen=280,
id_productivo=269) — el filtro de referentes en Consulta Padron no devolvio resultados.

### Diagnostico

Query utilizada para identificar todos los casos:
```sql
SELECT sr.id_origen, sr.apellido, sr.nombre, r.id AS id_productivo,
    (SELECT COUNT(*) FROM referentes_graduado
     WHERE referente_1 = r.id OR referente_2 = r.id OR referente_3 = r.id
    ) AS total_referidos_productivo,
    (...) AS total_en_staging
FROM st_referentes sr
JOIN referentes r ON ... -- CONVERT para evitar conflicto de collation
HAVING total_referidos_productivo = 0 AND total_en_staging > 0
```

### Casos corregidos

| id_origen | apellido | nombre | id_productivo | DNIs afectados |
|---|---|---|---|---|
| 6 | MUÑOZ | AGUSTINA | 6 | 1 |
| 16 | SIERRA | ANA | 16 | 4 |
| 24 | BASTERRECHEA | — | 24 | 1 |
| 37 | FOGLIA | CAROLINA | 37 | 1 |
| 54 | BLINDER | DANIEL | 53 | 1 |
| 55 | GALVALIZI | DANIEL | 54 | 1 |
| 84 | COSTAGLI | FLORENCIA | 82 | 1 |
| 85 | POLIMENI | FLORENCIA | 83 | 1 |
| 89 | PALUMBO | GABRIEL | 87 | 2 |
| 115 | BAUMAN | INGRID | 113 | 1 |
| 150 | GIL | LUCIANA | 145 | 1 |
| 169 | RULLI | MARIANA | 163 | 1 |
| 245 | RUSIL | YANINA | 236 | 1 |

Caso omitido — requiere revision manual:
- CARASSAI (id_origen=33): las tres posiciones de referentes_graduado ya tienen datos distintos a 250 para el DNI afectado (22735140). No se modifico.

Backup previo a la correccion: st_referentes_graduado_pre_correccion.

---

## Nivel mira en usuarios_fiscal — Junio 2026

Script: fiscaliz_mira.sql

### Cambios

```sql
ALTER TABLE usuarios_fiscal
    MODIFY nivel ENUM('superadmin','admin','mira') NOT NULL;

ALTER TABLE usuarios_fiscal
    ADD COLUMN tipo ENUM('cd','cp','rt','cs') NULL
    COMMENT 'Solo para nivel mira. Define el padron que puede ver.';
```

### Usuarios creados

| usuario | nivel | tipo |
|---|---|---|
| miracd | mira | cd |
| miracp | mira | cp |
| mirart | mira | rt |
| miracs | mira | cs |

El nivel mira accede solo al modulo consulta — listado de su padron en la
eleccion activa de su tipo, con filtro de voto (SI/NO/Todos) y buscador por
DNI, apellido o nombre. Solo lectura. Sin acceso a dashboard, listados completos,
observados, punteo ni administracion.

---

## Pendientes antes del pase a produccion real

- Prueba completa del flujo electoral con datos reales.
- Crear mesas, dias y activar elecciones desde la interfaz.
- Validacion de migracion votos_dia -> participacion_electoral al cierre.
- Caso CARASSAI (DNI 22735140) — revision manual de referentes.
- Vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
