# Log de migracion de datos

**Proyecto:** Fiscalizar
**Actualizado:** Junio 2026 (version final pre-produccion)

---

## Resumen de procesos

| Proceso | Fecha | Script |
|---|---|---|
| Migracion inicial | Febrero 2026 | Descartada |
| Segunda migracion | Marzo 2026 | — |
| Actualizacion padrones | Mayo 2026 | — |
| Cambios estructurales Fiscalizacion | Junio 2026 | fiscaliz_estructura_v2.sql |
| Nivel mira | Junio 2026 | fiscaliz_mira.sql |
| Correccion referentes | Junio 2026 | correccion_referentes.sql |
| Padron CC | Junio 2026 | fiscaliz_padron_cc.sql |
| Habilitacion individual de mesas | Junio 2026 | fiscaliz_activa_mesa.sql |

---

## Segunda migracion — Marzo 2026

### Resultados

| Tabla | Registros |
|---|---|
| personas | 19.709 |
| padron_cd | 19.521 |
| padron_cp | 4.554 |
| referentes_graduado | 19.709 |
| persona_partido | 1.371 |
| persona_trabajo | 2.150 |
| participacion_electoral | 11.974 |

### Problemas resueltos
- Collation: SET NAMES en db.php.
- UNION en INSERT: dos INSERT separados.
- SIN ESPACIO POLITICO en persona_partido: DELETE y reinsert.
- DNIs duplicados en st_votos_cd_24: DISTINCT.

---

## Actualizacion padrones 2026 — Mayo 2026

### Resultados

| Tabla | Registros |
|---|---|
| personas | 22.325 |
| padron_cd | 21.745 |
| padron_cp | 4.843 |
| padron_rt | 5.240 |
| padron_cs | 4.829 |
| auxiliares | 454 (CP+RT+CS) |

Sigla padron_cd reconstruida via st_dni_carrera.
13 casos con sigla NS. id=98 agregado a carreras.

---

## Cambios estructurales Fiscalizacion — Junio 2026

Script: fiscaliz_estructura_v2.sql

### Tabla elecciones
activa TINYINT reemplazado por estado ENUM('programada','activa','cerrada').

### Tabla mesas
- Agregado id_dia FK a dias_eleccion
- Eliminado id_eleccion (FK + columna)
- Campo habilitada deprecado

### Tabla votos_dia
- Eliminado id_eleccion
- Eliminado UNIQUE KEY (dni, id_eleccion)
- Creado UNIQUE KEY (dni, id_mesa)

### Nuevas tablas
dias_eleccion, usuarios_fiscal, punteo.

### Nuevas vistas
vista_fiscal_cd/cp/rt/cs (version inicial).

### Verificacion de integridad
- DNIs huerfanos en padron_rt: 0
- DNIs huerfanos en padron_cs: 0
- Diferencia votos CD 2024: 56 = dados de baja 2026 (confirmados)
- Diferencia votos CP 2024: 35 = dados de baja 2026 (confirmados)

---

## Nivel mira — Junio 2026

Script: fiscaliz_mira.sql

```sql
ALTER TABLE usuarios_fiscal
    MODIFY nivel ENUM('superadmin','admin','mira') NOT NULL;
ALTER TABLE usuarios_fiscal
    ADD COLUMN tipo ENUM('cd','cp','rt','cs','cc') NULL;
```

Usuarios creados: miracd, miracp, mirart, miracs, miracc.

---

## Correccion de referentes — Junio 2026

Script: correccion_referentes.sql

### Problema
14 referentes con id_origen != id_productivo no se migraron correctamente
a referentes_graduado. Detectado al buscar referidos de ESLAIMAN JUAN.

### Casos corregidos (13 de 14)

| id_origen | apellido | id_productivo | DNIs |
|---|---|---|---|
| 6 | MUÑOZ AGUSTINA | 6 | 1 |
| 16 | SIERRA ANA | 16 | 4 |
| 24 | BASTERRECHEA | 24 | 1 |
| 37 | FOGLIA CAROLINA | 37 | 1 |
| 54 | BLINDER DANIEL | 53 | 1 |
| 55 | GALVALIZI DANIEL | 54 | 1 |
| 84 | COSTAGLI FLORENCIA | 82 | 1 |
| 85 | POLIMENI FLORENCIA | 83 | 1 |
| 89 | PALUMBO GABRIEL | 87 | 2 |
| 115 | BAUMAN INGRID | 113 | 1 |
| 150 | GIL LUCIANA | 145 | 1 |
| 169 | RULLI MARIANA | 163 | 1 |
| 245 | RUSIL YANINA | 236 | 1 |

Caso pendiente revision manual:
- CARASSAI (DNI 22735140): tres posiciones ocupadas, no hay lugar libre.

Backup: st_referentes_graduado_pre_correccion.

---

## Padron CC — Junio 2026

Script: fiscaliz_padron_cc.sql

### Contexto
st_padron_cc_2026 tenia 4.504 registros. 153 DNIs no existian en personas
(auxiliares puros de CC sin presencia en otros padrones).

### Pasos
1. INSERT en personas de los 153 DNIs exclusivos de CC
2. CREATE TABLE padron_cc + INSERT desde staging
3. INSERT en auxiliares: 153 registros con id_carrera=5
4. ALTER TABLE elecciones/mesas/usuarios_fiscal: tipo incluye 'cc'
5. CREATE VIEW vista_fiscal_cc

### Resultados

| Tabla | Registros |
|---|---|
| personas | 22.478 (+153) |
| padron_cc | 4.504 |
| auxiliares CC | 153 |

### Problema de collation
vista_fiscal_cc genera error 1267 al filtrar por voto_2026 desde PHP.
Workaround en consulta.php: COLLATE utf8mb4_unicode_ci en el WHERE.
Pendiente: recrear la vista con COLLATE explicito.

### Correccion de datos
DNI 39466499: pertenece a FALAK AGUSTINA, no a CAMINITI LETICIA.
El error venia de st_padron_cd_2024. Corregido en personas, padron_cd y padron_cp.

---

## Habilitacion individual de mesas — Junio 2026

Script: fiscaliz_activa_mesa.sql

### Contexto
Se agrego soporte para deshabilitar mesas individualmente sin afectar
el resto del dia. El campo mesas.activa (DEFAULT 1) se activo en la logica.

### Cambios en vistas
Las cinco vistas vista_fiscal_* fueron recreadas agregando AND m.activa=1
en el subquery EXISTS que calcula VOTO_2026.

### Cambios en PHP
- login.php: AND m.activa=1 en combo y autenticacion
- buscar.php: AND m.activa=1 en subquery
- registrar_voto.php: AND m.activa=1 en verificacion
- listados.php: AND m.activa=1 en subquery
- dashboard.php: estado de mesas filtra d.habilitado=1 AND m.activa=1
  El conteo de votos NO filtra por dia — es acumulado de toda la eleccion.
- abm_elecciones.php: botones Habilitar/Deshabilitar y Eliminar por mesa

---

## Pendientes

- Prueba completa del flujo electoral con datos reales (martes).
- Validacion de migracion votos_dia -> participacion_electoral al cierre.
- Recrear vista_fiscal_cc con COLLATE explicito.
- Caso CARASSAI (DNI 22735140) — revision manual de referentes.
- Vistas vista_padron_rt y vista_padron_cs para Consulta Padron v2.
