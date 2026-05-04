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
| Actualizacion | Mayo 2026 | Nuevas tablas, catalogos ampliados, vinculos actualizados. |

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

## Actualizacion — Mayo 2026

### Baja de referentes

Se dieron de baja 25 referentes (activo = 0). Ids: 266, 195, 24, 87, 33, 37, 46, 53, 54, 67, 82, 83, 113, 117, 135, 143, 145, 163, 169, 213, 232, 236, 6, 12, 16.

Se ejecuto compactacion en `referentes_graduado`: las posiciones vacias se corrieron hacia la izquierda. Las posiciones sin referente se asignaron al id 250 (SIN REFERENTE) en lugar de NULL.

Se asigno id 250 en todas las posiciones vacias de `referentes_graduado`. Se insertaron filas con id 250 en las tres posiciones para personas que no tenian fila.

### Recreacion de vistas

Las vistas `vista_padron_cd` y `vista_padron_cp` fueron recreadas con:
- `AND r.activo = 1` en los tres JOIN a `referentes`. Los referentes con activo=0 no aparecen en el resultado.
- JOIN a `persona_sede` y `sedes` reemplazando el campo `sede_laboral` texto libre.
- JOIN a `persona_municipio` y `municipios`.

### Nuevas tablas de catalogos

**`sedes`** — 50 valores normalizados desde `fiscaliz_fiscalizar.padroncd24.sedelaboral`.

**`municipios`** — 84 valores normalizados desde `fiscaliz_fiscalizar.padroncd24.comuna_municipio`. Incluye comunas CABA (COMUNA 1-15), partidos GBA e interior PBA.

### Nuevas tablas de relacion

**`persona_sede`** — poblada desde `fiscaliz_fiscalizar.padroncd24` por join de texto exacto. Los DNIs sin sede y los que solo estan en padron_cp recibieron id_sede = 1 (SIN DATO).

| Resultado | Valor |
|---|---|
| Total filas | 19.709 |
| SIN DATO | 17.690 |
| Con sede | 2.019 |

**`persona_municipio`** — misma logica que persona_sede.

| Resultado | Valor |
|---|---|
| Total filas | 19.709 |
| SIN DATO | 18.951 |
| Con municipio | 758 |

**`sede_laboral`** — eliminada. Reemplazada por el par sedes/persona_sede.

### Altas en catalogos

| Catalogo | Altas | Total actual |
|---|---|---|
| `referentes` | 55 | 324 |
| `partidos` | 34 | 87 |
| `trabajos` | 30 | 105 |

### Actualizacion de vinculos desde Excel

Se cargo la tabla staging `padroncd_st` con 3.805 registros correspondientes a los empadronados que votaron en la ultima eleccion, con todos los vinculos actualizados y codificados.

Estructura de la staging:
```
dni | r1 | r2 | r3 | partido | trabajo | sede | municipio
```

Se ejecutaron UPDATE sobre las cinco tablas de relacion joineando por DNI. Los DNIs sin fila previa en `persona_partido` y `persona_trabajo` se insertaron.

| Tabla | Resultado |
|---|---|
| `referentes_graduado` | 3.805 filas actualizadas |
| `persona_partido` | 441 actualizadas + nuevas hasta 4.735 total |
| `persona_trabajo` | 1.383 actualizadas + nuevas hasta 4.572 total |
| `persona_sede` | 3.805 filas actualizadas |
| `persona_municipio` | 3.805 filas actualizadas |

La staging `padroncd_st` fue eliminada al finalizar.

---

## Pendientes antes del pase a produccion

- Validacion profunda de consistencia general de los datos.
- Recrear vistas con JOIN a persona_sede y persona_municipio (pendiente).
- Actualizar filtros.php con combos de sede y municipio (pendiente).
- Crear tablas de Fiscalizacion (mesas, usuarios_fiscal, votos_dia).
- Crear usuario superadmin en usuarios_fiscal.
