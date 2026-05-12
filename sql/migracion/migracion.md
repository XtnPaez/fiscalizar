# Log de migracion de datos

**Proyecto:** Fiscalizar
**Actualizado:** Junio 2026

---

## Contexto general

Se realizaron tres procesos de migracion y actualizacion sucesivos, mas un proceso
de cambios estructurales para el modulo Fiscalizacion.

| Proceso | Fecha | Descripcion |
|---|---|---|
| Migracion inicial | Febrero 2026 | Consolidacion desde bases anteriores. Descartada por inconsistencias. |
| Segunda migracion | Marzo 2026 | Desde tablas staging consolidadas offline. Base actual. |
| Actualizacion padrones y vinculos | Mayo 2026 | Padrones 2026, nuevas tablas, catalogos ampliados, vinculos actualizados. |
| Cambios estructurales Fiscalizacion | Junio 2026 | Nuevas tablas y vistas para el modulo electoral. |

Ninguna de las bases de origen fue modificada. Quedan en modo solo lectura.

| Base de origen | Rol |
|---|---|
| `fiscaliz_fiscalizar` | Base anterior del sistema de fiscalizacion. Fuente de padrones y participacion electoral. |
| `fiscaliz_graduados` | Base de trabajo 2024. Fuente del padron CP enriquecido. |

---

## Segunda migracion — Marzo 2026

### Cambios de diseno incorporados

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

Se dieron de baja 25 referentes (activo = 0). Se ejecuto compactacion en referentes_graduado.
Las posiciones vacias se corrieron hacia la izquierda. Las posiciones sin referente se
asignaron al id 250 (SIN REFERENTE). Se insertaron filas con id 250 en las tres posiciones
para personas sin fila.

### Recreacion de vistas

Las vistas vista_padron_cd y vista_padron_cp fueron recreadas con JOIN a persona_sede,
sedes, persona_municipio y municipios. Se agrego AND r.activo = 1 en los tres JOIN a referentes.

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
| `st_padron_profesores_cp_2026` | — | Para cruce de bajas |
| `st_actualizacion_referentes_2026` | 193 | Referentes y partidos nuevos |
| `st_bajas_padron_cd_2026` | 111 | DNIs que salen del padron CD |
| `st_bajas_padron_cp_2026` | 100 | DNIs que salen del padron CP |
| `st_padron_cd_2024` | 19.521 | Backup pre-actualizacion |
| `st_padron_cp_2024` | 4.554 | Backup pre-actualizacion |

### Reconstruccion del campo sigla en padron_cd

El padron CD 2026 no incluye la sigla de carrera. Se reconstruyo cruzando contra
los cinco padrones de carrera via st_dni_carrera.

| Fuente | DNIs resueltos |
|---|---|
| Padrones de carrera 2026 | 21.394 |
| Padron CD 2024 | 335 |
| Correccion manual | 3 |
| NS (no identificados) | 13 |
| **Total** | **21.745** |

Se agrego id=98 (NS, No identificada) al catalogo carreras.

### Resultados finales

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

## Cambios estructurales para Fiscalizacion — Junio 2026

Script aplicado: fiscaliz_estructura_v2.sql

### Cambios en tabla elecciones

El campo `activa TINYINT(1)` fue reemplazado por `estado ENUM('programada','activa','cerrada')`.

- Elecciones 1-6 (historicas): estado = 'cerrada'
- Elecciones 7-8 (RT 2026, CS 2026): estado = 'programada'

Razon: el campo booleano no distinguia entre una eleccion futura (programada)
y una eleccion historica terminada (cerrada). El ENUM expresa el ciclo de vida completo.

### Nuevas tablas

**`dias_eleccion`** — nivel intermedio entre elecciones y mesas.
La habilitacion opera a nivel dia, no a nivel mesa individual.

**`mesas`** (modificada) — se agrego id_dia como FK a dias_eleccion.
Se elimino id_eleccion (columna redundante — la eleccion se obtiene via id_dia).
El campo habilitada quedo deprecado — la habilitacion viene de dias_eleccion.habilitado.

**`votos_dia`** — registro en tiempo real de votos del dia.
Se elimino id_eleccion (la eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones).
Se elimino el UNIQUE KEY (dni, id_eleccion) y se creo (dni, id_mesa).
Se eliminaron los indices asociados a id_eleccion.

**`usuarios_fiscal`** — usuarios admin y superadmin del modulo Fiscalizacion.
Independiente de la tabla usuarios de Consulta Padron.

**`punteo`** — punteo de nuestra lista por corte y por mesa.
UNIQUE KEY (id_mesa, numero_corte).
El numero_corte se asigna automaticamente (MAX + 1 por mesa).

### Nuevas vistas

Cuatro vistas creadas para el modulo Fiscalizacion. No reemplazan ni modifican
las vistas de Consulta Padron.

- vista_fiscal_cd — fuente padron_cd — VOTO_2026 desde votos_dia — incluye CARRERA
- vista_fiscal_cp — fuente padron_cp — VOTO_2026 desde votos_dia — incluye AUXILIAR
- vista_fiscal_rt — fuente padron_rt — VOTO_2026 desde votos_dia — AUXILIAR desde auxiliares id_carrera=3
- vista_fiscal_cs — fuente padron_cs — VOTO_2026 desde votos_dia — AUXILIAR desde auxiliares id_carrera=1

Todas usan EXISTS + subquery sobre mesas de la eleccion activa del tipo correspondiente
para evitar duplicados cuando una eleccion tiene mas de un dia.

### Problemas encontrados y resolucion

- **Campo id_eleccion en mesas**: tenia FK fk_mesas_elecciones que requirio DROP FOREIGN KEY antes del DROP COLUMN.
- **Campo id_eleccion en votos_dia**: tenia UNIQUE KEY uk_voto_dni_eleccion e indice idx_id_eleccion que requirieron eliminarse antes del DROP COLUMN.
- **Mesas de prueba**: las 8 mesas cargadas manualmente quedaron con id_dia = NULL. Se eliminaron con reset_total.sql y se recrean desde la interfaz.

### Verificacion de integridad

Se verifico que todos los DNIs de padron_rt y padron_cs existen en personas:

```sql
SELECT COUNT(*) FROM padron_rt prt
LEFT JOIN personas p ON prt.dni = p.dni WHERE p.dni IS NULL;
-- Resultado: 0

SELECT COUNT(*) FROM padron_cs pcs
LEFT JOIN personas p ON pcs.dni = p.dni WHERE p.dni IS NULL;
-- Resultado: 0
```

Se verifico la consistencia de votos 2024 en las vistas de Consulta Padron:

- CD: 3.805 votos en participacion_electoral, 3.749 en vista (56 dados de baja en 2026 — todos confirmados en st_bajas_padron_cd_2026).
- CP: 1.394 votos en participacion_electoral, 1.359 en vista (35 dados de baja en 2026 — todos confirmados en st_bajas_padron_cp_2026).
- Diferencias esperadas y correctas.

---

## Pendientes antes del pase a produccion real

- Prueba completa del flujo electoral con datos reales (activar eleccion, habilitar dias, loguear fiscales, registrar votos, cerrar y migrar).
- Crear vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
- Evaluar incorporacion de votos RT y CS en vistas de Consulta Padron (version 2).
