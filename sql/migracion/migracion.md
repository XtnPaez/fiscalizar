# Log de migracion de datos

**Proyecto:** Fiscalizar
**Actualizado:** Junio 2026

---

## Resumen de procesos

| Proceso | Fecha | Descripcion |
|---|---|---|
| Migracion inicial | Febrero 2026 | Descartada por inconsistencias. |
| Segunda migracion | Marzo 2026 | Base actual. |
| Actualizacion padrones y vinculos | Mayo 2026 | Padrones 2026, nuevas tablas, catalogos. |
| Cambios estructurales Fiscalizacion | Junio 2026 | Nuevas tablas y vistas electorales. |
| Nivel mira | Junio 2026 | Nuevo rol de acceso a padron especifico. |
| Correccion referentes | Junio 2026 | 14 referentes mal migrados corregidos. |
| Padron CC | Junio 2026 | padron_cc, auxiliares CC, vista_fiscal_cc. |

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

- Conflicto de collation resuelto con SET NAMES en db.php.
- UNION en INSERT no funciona en phpMyAdmin. Dos INSERT separados.
- SIN ESPACIO POLITICO incluido por error en persona_partido. Corregido.
- DNIs duplicados en st_votos_cd_24. Resuelto con DISTINCT.

---

## Actualizacion de padrones 2026 — Mayo 2026

### Resultados

| Tabla | Registros |
|---|---|
| personas | 22.325 |
| padron_cd | 21.745 |
| padron_cp | 4.843 |
| padron_rt | 5.240 |
| padron_cs | 4.829 |
| auxiliares | 454 (CP+RT+CS) |

Sigla en padron_cd reconstruida via st_dni_carrera. 13 casos con sigla NS.
id=98 (NS) agregado al catalogo carreras.

---

## Cambios estructurales Fiscalizacion — Junio 2026

Script: fiscaliz_estructura_v2.sql

### Tabla elecciones
Campo activa reemplazado por estado ENUM('programada','activa','cerrada').
- Elecciones 1-6: cerrada
- Elecciones 7-8 (RT/CS 2026): programada

### Nuevas tablas
dias_eleccion, mesas (modificada), votos_dia (modificada), usuarios_fiscal, punteo.

### Cambios en mesas
- Agregado id_dia FK a dias_eleccion
- Eliminado id_eleccion (FK fk_mesas_elecciones + columna)
- Campo habilitada deprecado

### Cambios en votos_dia
- Eliminado id_eleccion
- Eliminado UNIQUE KEY (dni, id_eleccion) e indice idx_id_eleccion
- Creado UNIQUE KEY (dni, id_mesa)

### Nuevas vistas
vista_fiscal_cd, vista_fiscal_cp, vista_fiscal_rt, vista_fiscal_cs.

### Verificacion de integridad
- DNIs de padron_rt sin par en personas: 0
- DNIs de padron_cs sin par en personas: 0
- CD: 56 votos 2024 fuera de vista = dados de baja 2026 (confirmados en st_bajas_padron_cd_2026)
- CP: 35 votos 2024 fuera de vista = dados de baja 2026 (confirmados en st_bajas_padron_cp_2026)

---

## Nivel mira en usuarios_fiscal — Junio 2026

Script: fiscaliz_mira.sql

### Cambios

```sql
ALTER TABLE usuarios_fiscal
    MODIFY nivel ENUM('superadmin','admin','mira') NOT NULL;

ALTER TABLE usuarios_fiscal
    ADD COLUMN tipo ENUM('cd','cp','rt','cs','cc') NULL;
```

### Usuarios creados

| usuario | nivel | tipo |
|---|---|---|
| miracd | mira | cd |
| miracp | mira | cp |
| mirart | mira | rt |
| miracs | mira | cs |
| miracc | mira | cc |

El nivel mira accede solo al modulo consulta. Solo lectura. Sin administracion.

---

## Correccion de referentes mal migrados — Junio 2026

Script: correccion_referentes.sql

### Problema
14 referentes con asignaciones en staging no se migraron a referentes_graduado.
El id_origen de staging se copio directamente en lugar del id productivo.

### Casos corregidos

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

Caso omitido — revision manual pendiente:
- CARASSAI (DNI 22735140): tres posiciones ocupadas con datos distintos a 250.

Backup: st_referentes_graduado_pre_correccion.

---

## Padron CC — Junio 2026

Script: fiscaliz_padron_cc.sql

### Contexto
padron_cc existia solo en staging (st_padron_cc_2026, 4.504 registros).
No tenia tabla productiva ni cruce con personas.

### Verificacion previa
- DNIs de st_padron_cc_2026 no existentes en personas: 153
- Esos 153 no estaban en padron_cd ni en auxiliares — son auxiliares puros de CC
  que no cruzaban con ningun otro padron productivo.

### Pasos ejecutados

1. INSERT en personas de los 153 DNIs exclusivos de CC
2. CREATE TABLE padron_cc con FK a personas
3. INSERT en padron_cc desde st_padron_cc_2026 (4.504 registros)
4. INSERT en auxiliares los 153 con id_carrera=5 (CC)
5. ALTER TABLE elecciones MODIFY tipo para incluir 'cc'
6. ALTER TABLE mesas MODIFY tipo para incluir 'cc'
7. ALTER TABLE usuarios_fiscal MODIFY tipo para incluir 'cc'
8. CREATE VIEW vista_fiscal_cc

### Resultados

| Tabla | Registros |
|---|---|
| personas | 22.478 (+153) |
| padron_cc | 4.504 |
| auxiliares CC | 153 |

### Problema de collation en vista_fiscal_cc
La vista mezcla padron_cc (utf8mb4_spanish_ci) con tablas staging en
utf8mb4_unicode_ci, generando error 1267 al filtrar por voto_2026 desde PHP.

Solucion aplicada en consulta.php:
```php
$where[] = "voto_2026 COLLATE utf8mb4_unicode_ci = 'SI'";
```

Pendiente: recrear vista_fiscal_cc con COLLATE explicito en todos los campos
para eliminar el workaround del PHP.

---

## Pendientes antes de produccion real

- Prueba completa del flujo electoral con datos reales (martes).
- Validacion de migracion votos_dia -> participacion_electoral al cierre.
- Caso CARASSAI (DNI 22735140) — revision manual de referentes.
- Recrear vista_fiscal_cc con COLLATE explicito.
- Vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
