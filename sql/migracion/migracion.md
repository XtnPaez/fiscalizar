# Log de migración de datos

**Proyecto:** Fiscalizar  
**Actualizado:** Mayo 2026  

---

## Contexto general

Se realizaron tres procesos de migracion y actualizacion sucesivos.

| Proceso | Fecha | Descripcion |
|---|---|---|
| Migracion inicial | Febrero 2026 | Consolidacion desde bases anteriores. Descartada por inconsistencias. |
| Segunda migracion | Marzo 2026 | Desde tablas staging consolidadas offline. Base actual. |
| Actualizacion padrones y vinculos | Mayo 2026 | Padrones 2026, nuevas tablas, catalogos ampliados, vinculos actualizados. |

Ninguna de las bases de origen fue modificada. Quedan en modo solo lectura.

| Base de origen | Rol |
|---|---|
| `fiscaliz_fiscalizar` | Base anterior del sistema de fiscalizacion. Fuente de padrones y participacion electoral. |
| `fiscaliz_graduados` | Base de trabajo 2024. Fuente del padron CP enriquecido. |

---

## Segunda migracion — Marzo 2026

### Cambios de diseño incorporados

| Cambio | Detalle |
|---|---|
| `carreras` sin AUTO_INCREMENT | ids con significado propio. id=99 para SIN DATO. |
| `sede_laboral` simplificada | Solo dni y sede. Luego reemplazada por persona_sede. |
| `st_votos_mesa` renombrada | Pasa a st_votos_cp_24. |
| `mapeo_referentes` eliminada | Era temporal. Mapeo resuelto en queries. |
| `usuarios` agregada | Faltaba en DDL anterior. |

### Tablas staging

| Tabla | Registros | Rol |
|---|---|---|
| `st_carreras` | 6 | Catalogo de carreras |
| `st_referentes` | 269 | Referentes con id_origen |
| `st_partidos` | 53 | Partidos con id_origen |
| `st_trabajo` | 75 | Trabajos con id_origen |
| `st_padron_cd_datos` | 19.528 | Padron CD enriquecido |
| `st_padron_cp_datos` | 4.560 | Padron CP enriquecido |
| `st_auxiliares_cp` | 454 | Auxiliares CP |
| `st_votos_cd_24` | 3.826 | Votos CD 2024 |
| `st_votos_cp_24` | 1.400 | Votos CP 2024 |

### Resultados

| Tabla | Registros |
|---|---|
| `personas` | 19.709 |
| `padron_cd` | 19.521 |
| `padron_cp` | 4.554 |
| `referentes` | 269 |
| `partidos` | 53 |
| `trabajos` | 75 |
| `referentes_graduado` | 19.709 |
| `persona_partido` | 1.371 |
| `persona_trabajo` | 2.150 |
| `participacion_electoral` | 11.974 |

### Problemas encontrados y resolucion

- **Conflicto de collation** entre tablas productivas (utf8mb4_spanish_ci) y staging (utf8mb4_unicode_ci). Resuelto con COLLATE explicito en los joins y luego con SET NAMES en db.php.
- **UNION en INSERT** no funciona en phpMyAdmin. Se ejecutaron dos INSERT separados para referentes_graduado.
- **SIN ESPACIO POLITICO incluido por error** en persona_partido. Se ejecuto DELETE y se reinserto con filtro.
- **DNIs duplicados** en st_votos_cd_24. Resuelto con DISTINCT.

---

## Actualizacion de catalogos y vistas — Mayo 2026 (primera parte)

### Baja de referentes

Se dieron de baja 25 referentes (activo = 0). Se ejecuto compactacion en `referentes_graduado`: las posiciones vacias se corrieron hacia la izquierda. Las posiciones sin referente se asignaron al id 250 (SIN REFERENTE) en lugar de NULL. Se insertaron filas con id 250 en las tres posiciones para personas que no tenian fila.

### Recreacion de vistas

Las vistas `vista_padron_cd` y `vista_padron_cp` fueron recreadas con JOIN a `persona_sede`, `sedes`, `persona_municipio` y `municipios`, reemplazando el campo `sede_laboral` texto libre. Se agrego `AND r.activo = 1` en los tres JOIN a `referentes`.

### Nuevas tablas

**`sedes`** — 50 valores normalizados. id=1 reservado para SIN DATO.
**`municipios`** — 84 valores normalizados. id=1 reservado para SIN DATO.
**`persona_sede`** — 19.709 filas. 2.019 con sede real, resto SIN DATO.
**`persona_municipio`** — 19.709 filas. 758 con municipio real, resto SIN DATO.

### Altas en catalogos

| Catalogo | Altas | Total |
|---|---|---|
| `referentes` | 55 | 324 |
| `partidos` | 34 | 87 |
| `trabajos` | 30 | 105 |

---

## Actualizacion de padrones 2026 — Mayo 2026 (segunda parte)

### Nuevas tablas staging

| Tabla | Registros | Descripcion |
|---|---|---|
| `st_padron_cd_2026` | 21.745 | Padron CD 2026 oficial depurado |
| `st_padron_cp_2026` | 4.843 | Padron CP 2026 oficial |
| `st_padron_rt_2026` | 5.240 | Padron RT 2026 oficial |
| `st_padron_cs_2026` | 4.829 | Padron CS 2026 oficial |
| `st_padron_profesores_cp_2026` | — | Padron de profesores CP para cruce de bajas |
| `st_actualizacion_referentes_2026` | 193 | Referentes y partidos nuevos por DNI |
| `st_bajas_padron_cd_2026` | 111 | DNIs que salen del padron CD |
| `st_bajas_padron_cp_2026` | 100 | DNIs que salen del padron CP |
| `st_padron_cd_2024` | 19.521 | Backup padron CD pre-actualizacion |
| `st_padron_cp_2024` | 4.554 | Backup padron CP pre-actualizacion |
| `st_padron_cp_pre_auxiliares_2026` | 4.843 | Backup padron CP pre-marcado auxiliares |
| `st_persona_partido_pre_actualizacion_2026` | 1.371 | Backup persona_partido pre-actualizacion |

### Depuracion de staging CD 2026

Se encontraron 5 DNIs duplicados en `st_padron_cd_2026`. Se eliminaron las filas de menor prioridad:

| Caso | DNI | Resolucion |
|---|---|---|
| Nombre abreviado | 33190278 | Se mantuvo el de menor orden |
| Nombre abreviado | 38684141 | Se mantuvo el de menor orden |
| Apellido con error | 41672123 | Se mantuvo VILLAPLANA (orden 21092) |
| DNI invalido | 94200094 | Eliminados ambos registros |
| DNI invalido | 95856298 | Eliminados ambos registros |

### Cruce de auxiliares en staging

| Padron | Auxiliares identificados |
|---|---|
| CP | 175 |
| RT | 118 |
| CS | 161 |
| **Total** | **454** |

Se identificaron 4 DNIs auxiliares en mas de una carrera simultaneamente.

### Orden de ejecucion

1. Insertar 2.616 personas nuevas en `personas`
2. Backup `padron_cd` → `st_padron_cd_2024`
3. TRUNCATE + repoblar `padron_cd` desde `st_padron_cd_2026` (21.745 filas)
4. Backup `padron_cp` → `st_padron_cp_2024`
5. TRUNCATE + repoblar `padron_cp` desde `st_padron_cp_2026` (4.843 filas)
6. UPDATE `padron_cp` SET auxiliar=1 donde DNI no esta en `padron_cd` (175 filas)
7. INSERT `auxiliares` desde CP (175), RT (118), CS (161)
8. INSERT `padron_rt` desde `st_padron_rt_2026` (5.240 filas)
9. INSERT `padron_cs` desde `st_padron_cs_2026` (4.829 filas)
10. Extender ENUM `elecciones.tipo` a cd/cp/rt/cs
11. INSERT elecciones RT 2026 (id=7) y CS 2026 (id=8)

### Actualizacion de referentes y partidos

Se cargo `st_actualizacion_referentes_2026` con 193 registros (dni, id_referente, id_partido).

**Referentes:**
- 18 DNIs actualizados con desplazamiento (ref nuevo → ref1, anterior → ref2)
- 166 DNIs sin fila previa: INSERT con referente_1 = nuevo
- 7 saltados (referente ya presente en alguna posicion)
- 2 saltados por DNI no encontrado en `personas`

**Partidos:**
- 2 DNIs actualizados (partido distinto al existente)
- 189 DNIs insertados (no tenian partido previo)
- 11 saltados (mismo partido ya asignado)

**Problema encontrado:** el primer INSERT de partidos inserto filas duplicadas por error de logica en la query. Se trunco `persona_partido`, se reconstruyo desde `st_padron_cd_datos` (1.371 filas) y se reaplico el listado correctamente. El partido NUEVO ENCUENTRO no matcheo por cambio de nombre a EX NUEVO ENCUENTRO — se insertaron los 4 DNIs afectados manualmente.

### Actualizacion de vistas

Las vistas `vista_padron_cd` y `vista_padron_cp` fueron recreadas con:
- COALESCE en todos los campos opcionales: devuelven texto explicito (SIN REFERENTE, SIN PARTIDO, SIN TRABAJO, SIN SEDE, SIN MUNICIPIO) en lugar de NULL.
- Campo `auxiliar` en `vista_padron_cp` devuelve SI/NO como texto en lugar de 1/0.

### Reconstruccion del campo sigla en padron_cd

El padron CD 2026 no incluye la sigla de carrera. Se reconstruyo cruzando contra los cinco padrones de carrera via la tabla staging `st_dni_carrera`.

**Tablas staging adicionales cargadas:**

| Tabla | Registros |
|---|---|
| `st_padron_ts_2026` | 2.936 |
| `st_padron_cc_2026` | 4.504 |
| `st_dni_carrera` | 22.103 |

TS y CC quedan solo en staging — no tienen tabla productiva.

**Proceso:**

1. Se pobló `st_dni_carrera` uniendo los cinco padrones de carrera con prioridad CP > CS > RT > TS > CC. Un DNI con múltiples carreras toma la de mayor prioridad.
2. UPDATE `padron_cd` desde `st_dni_carrera` por DNI → 21.394 filas resueltas.
3. Los 351 sin match se buscaron en `st_padron_cd_2024` → 335 filas resueltas.
4. Tres casos con DNI erróneo identificados por apellido y nombre → corregidos manualmente (FERRARIO EMILIA CP, MARTINEZ CLARISA LORENA TS, PALUMBO NANCY EMILSE CC).
5. Se agregó id=98 (NS, No identificada) al catalogo `carreras`.
6. Los 13 DNIs restantes se marcaron con sigla NS.

**Resultado:**

| Fuente | DNIs resueltos |
|---|---|
| Padrones de carrera 2026 | 21.394 |
| Padron CD 2024 | 335 |
| Correccion manual | 3 |
| NS (no identificados) | 13 |
| **Total** | **21.745** |

---

| Tabla | Registros | Estado |
|---|---|---|
| `personas` | 22.325 | ✅ |
| `padron_cd` | 21.745 | ✅ |
| `padron_cp` | 4.843 | ✅ |
| `padron_rt` | 5.240 | ✅ |
| `padron_cs` | 4.829 | ✅ |
| `auxiliares` | 454 | ✅ |
| `elecciones` | 8 | ✅ |
| `referentes_graduado` | 22.325 | ✅ |
| `persona_partido` | 1.560 | ✅ |

---

## Pendientes antes del pase a produccion

- Validacion profunda de consistencia general de los datos.
- Crear tablas de Fiscalizacion (mesas, usuarios_fiscal, votos_dia).
- Crear vistas vista_padron_rt y vista_padron_cs.
- Crear usuario superadmin en usuarios_fiscal.
