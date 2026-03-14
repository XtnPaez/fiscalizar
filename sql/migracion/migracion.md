# Log de migración de datos

**Proyecto:** Fiscalizar  
**Fecha:** Marzo 2026  
**Ejecutado por:** superadmin  

---

## Contexto

Se realizaron dos procesos de migración sucesivos.

El primero (migración inicial, febrero 2026) consolidó datos desde dos bases anteriores hacia `fiscaliz_padron`. Al cruzar los datos se encontraron contradicciones e inconsistencias entre las fuentes, por lo que se decidió realizar una segunda migración desde cero, trabajando offline con los datos crudos consolidados manualmente antes de subir.

El segundo proceso (esta migración, marzo 2026) partió de tablas staging consolidadas offline, truncó y repobló `fiscaliz_padron` completamente, e incorporó mejoras al diseño del esquema.

Ninguna de las bases de origen fue modificada. Ambas quedan en modo solo lectura como fuente histórica.

| Base de origen | Rol |
|---|---|
| `fiscaliz_fiscalizar` | Base anterior del sistema de fiscalización. Fuente de padrones y participación electoral. |
| `fiscaliz_graduados` | Base de trabajo 2024. Fuente del padrón CP enriquecido y tablas auxiliares. |

---

## Cambios de diseño incorporados en esta migración

Respecto al esquema anterior, se incorporaron los siguientes cambios antes de repoblar:

| Cambio | Detalle |
|---|---|
| `carreras` sin AUTO_INCREMENT | Los ids tienen significado propio. id 1-5 carreras reales, id 99 reservado para SIN DATO. |
| `carreras` agrega id=99 / SD | Para auxiliares de otras facultades que no tienen carrera en Sociales. |
| `sede_laboral` simplificada | Queda solo con `dni` y `sede`. Sin fecha, sin apellido/nombre. |
| `st_votos_mesa` renombrada | Pasa a llamarse `st_votos_cp_24` para consistencia con `st_votos_cd_24`. |
| `mapeo_referentes` eliminada | Era temporal de la migración anterior. En el nuevo flujo el mapeo se resuelve en las queries. |
| `usuarios` agregada | Faltaba en el DDL anterior. Se agrega vacía, se puebla en la etapa de desarrollo. |

---

## Tablas staging

Todas las tablas staging (prefijo `st_`) fueron consolidadas offline antes de subir. No tienen claves foráneas declaradas. Los joins contra las tablas productivas se resuelven en las queries de población.

| Tabla staging | Registros | Rol |
|---|---|---|
| `st_carreras` | 6 | Catálogo de carreras incluyendo id=99/SD |
| `st_referentes` | 269 | Catálogo de referentes con id_origen de la fuente |
| `st_partidos` | 53 | Catálogo de partidos con id_origen de la fuente |
| `st_trabajo` | 75 | Catálogo de trabajos con id_origen de la fuente |
| `st_padron_cd_datos` | 19.528 | Padrón CD enriquecido con referentes, partido, trabajo y votos históricos |
| `st_padron_cp_datos` | 4.560 | Padrón CP enriquecido con referentes, partido, trabajo y votos históricos |
| `st_auxiliares_cp` | 454 | Auxiliares CP. Subconjunto de st_padron_cp_datos para filtrado |
| `st_votos_cd_24` | 3.826 | Votos CD elección 2024 |
| `st_votos_cp_24` | 1.400 | Votos CP elección 2024 |
| `st_siet_2026` | 8.411 | Empleados UBA según SIET 2026 |
| `st_ucr_caba_2026` | 105.186 | Afiliados UCR CABA 2026 |
| `st_ucr_pba_2024` | 657.808 | Afiliados UCR PBA 2024 |

---

## Orden de migración

El orden es obligatorio por las claves foráneas. No puede alterarse.

1. `carreras` — INSERT inicial incluido en el DDL
2. `elecciones` — INSERT inicial incluido en el DDL
3. `personas`
4. `referentes`
5. `partidos`
6. `trabajos`
7. `padron_cd`
8. `padron_cp`
9. `referentes_graduado`
10. `persona_partido`
11. `persona_trabajo`
12. `participacion_electoral`

---

## Detalle por tabla

---

### `carreras` y `elecciones`

Pobladas via INSERT inicial incluido en el DDL. No requieren query de población separada.

**Resultado carreras:** 6 registros (5 carreras + id=99 SIN DATO).  
**Resultado elecciones:** 6 registros (CP 2017, CP 2019, CD 2021, CP 2021, CD 2024, CP 2024).

---

### `personas`

**Fuente:** unión de `st_padron_cd_datos` y `st_padron_cp_datos`.

**Criterio:** un registro por DNI. En caso de DNI compartido entre padrones, los datos de nombre y apellido provienen de `st_padron_cd_datos`. En caso de DNI duplicado dentro de cada staging, se tomó el registro de menor orden/id.

**Resultado:** 19.709 registros.

---

### `referentes`

**Fuente:** `st_referentes`.

**Criterio:** inserción ordenada por `id_origen` para consistencia en el mapeo viejo→nuevo. Los flags `cd` y `cp` se convirtieron de ENUM SI/NO a TINYINT 1/0.

**Problema encontrado:** conflicto de collation entre tablas productivas (utf8mb4_spanish_ci) y staging (utf8mb4_unicode_ci). Se resolvió con `COLLATE utf8mb4_unicode_ci` explícito en los joins. Este problema se repite en todas las queries que joinean tablas productivas contra staging.

**Resultado:** 269 registros.

---

### `partidos`

**Fuente:** `st_partidos`.

**Misma lógica que `referentes`.**

**Resultado:** 53 registros.

---

### `trabajos`

**Fuente:** `st_trabajo`.

**Misma lógica que `referentes`.**

**Resultado:** 75 registros.

---

### `padron_cd`

**Fuente:** `st_padron_cd_datos`.

**Criterio:** en caso de DNI duplicado se tomó el registro de menor orden. Solo se insertaron DNIs presentes en `personas`.

**Resultado:** 19.521 registros.

---

### `padron_cp`

**Fuente:** `st_padron_cp_datos`.

**Criterio:** en caso de DNI duplicado se tomó el registro de menor id. Campo `auxiliar` convertido de ENUM SI/NO a TINYINT 1/0. Solo se insertaron DNIs presentes en `personas`.

**Resultado:** 4.554 registros.

---

### `referentes_graduado`

**Fuente:** `st_padron_cp_datos` y `st_padron_cd_datos`.

**Criterio:** se insertaron primero los de CP. Luego los de CD que no tenían fila todavía. El mapeo de `id_origen` a `id` nuevo se resolvió via join por apellido y nombre entre `st_referentes` y `referentes`. Solo se insertaron registros con al menos un referente no nulo.

**Problema encontrado:** UNION dentro de INSERT no funciona en phpMyAdmin. Se ejecutaron dos INSERT separados.

**Resultado:** 19.709 registros. Uno por cada persona en `personas`.

---

### `persona_partido`

**Fuente:** `st_padron_cd_datos`.

**Criterio:** se excluyó `SIN ESPACIO POLITICO` (id_origen = 10048 en `st_partidos`). Quien no tiene partido no tiene fila en esta tabla. El mapeo id_origen→id nuevo se resolvió via join por nombre.

**Problema encontrado:** primera inserción incluyó SIN ESPACIO POLITICO por error. Se ejecutó DELETE y se reinsertó con el filtro correcto.

**Resultado:** 1.371 registros.

---

### `persona_trabajo`

**Fuente:** `st_padron_cd_datos`.

**Criterio:** se excluyó `SIN DATO` (id_origen = 20090 en `st_trabajo`). Misma lógica que `persona_partido`.

**Resultado:** 2.150 registros.

---

### `participacion_electoral`

**Fuente y criterio por elección:**

| Elección | id | Fuente | Campo origen |
|---|---|---|---|
| CP 2017 | 1 | `st_padron_cp_datos` | `voto17 = 'SI'` |
| CP 2019 | 2 | `st_padron_cp_datos` | `voto19 = 'SI'` |
| CD 2021 | 3 | `st_padron_cd_datos` | `voto21 = 'SI'` |
| CP 2021 | 4 | `st_padron_cp_datos` | `voto21 = 'SI'` |
| CD 2024 | 5 | `st_votos_cd_24` | JOIN con `st_padron_cd_datos` por `id_padron = orden` |
| CP 2024 | 6 | `st_votos_cp_24` | JOIN con `st_padron_cp_datos` por `id_padron = id` |

**Problema encontrado:** DNIs duplicados en `st_votos_cd_24` generaron error de clave única. Se resolvió con DISTINCT en el SELECT.

**Nota sobre fechas:** `fecha_registro` se cargó como NULL en todos los registros históricos. Solo se registrará con fecha real en elecciones futuras durante el día de la elección.

**Resultado:** 11.974 registros.

---

## Verificación de vistas

Ejecutadas al cierre de la migración:

```sql
SELECT COUNT(*) FROM vista_padron_cd;  -- 19.521
SELECT COUNT(*) FROM vista_padron_cp;  -- 4.554
```

Ambas vistas funcionan correctamente.

---

## Resumen de registros finales

| Tabla | Registros | Estado |
|---|---|---|
| `personas` | 19.709 | ✅ Migrado |
| `padron_cd` | 19.521 | ✅ Migrado |
| `padron_cp` | 4.554 | ✅ Migrado |
| `carreras` | 6 | ✅ Cargado |
| `referentes` | 269 | ✅ Migrado |
| `partidos` | 53 | ✅ Migrado |
| `trabajos` | 75 | ✅ Migrado |
| `elecciones` | 6 | ✅ Cargado |
| `referentes_graduado` | 19.709 | ✅ Migrado |
| `persona_partido` | 1.371 | ✅ Migrado |
| `persona_trabajo` | 2.150 | ✅ Migrado |
| `participacion_electoral` | 11.974 | ✅ Migrado |
| `sede_laboral` | 0 | ⏳ Pendiente listado |
| `usuarios` | 0 | ⏳ Se crea en desarrollo |

---

## Pendientes antes del pase a producción

- Cargar `sede_laboral` cuando el administrador tenga el listado tuneado.
- Crear usuario superadmin en `usuarios` antes de arrancar el desarrollo.
- Validación profunda de consistencia general de los datos migrados.

---

## Resumen

Se repobló `fiscaliz_padron` desde cero a partir de tablas staging consolidadas offline. Los principales problemas encontrados fueron: conflicto de collation entre tablas productivas y staging (resuelto con COLLATE explícito en los joins), inclusión incorrecta de valores SIN DATO en `persona_partido` (resuelto con filtro por id_origen), y duplicados en la tabla de votos CD 2024 (resuelto con DISTINCT). La base quedó migrada, las vistas verificadas y lista para el desarrollo de la etapa Consulta Padrón.
