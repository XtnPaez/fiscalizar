# Log de migración de datos

**Proyecto:** Fiscalizar  
**Fecha:** Marzo 2026  
**Ejecutado por:** superadmin  

---

## Contexto

La migración tomó datos de dos bases anteriores y los consolidó en `fiscaliz_padron`. Ninguna de las bases de origen fue modificada. Ambas quedan en modo solo lectura como fuente histórica.

| Base de origen | Rol |
|---|---|
| `fiscaliz_fiscalizar` | Base anterior del sistema de fiscalización. Fuente de padrones y participación electoral. |
| `fiscaliz_graduados` | Base de trabajo 2024. Fuente del padrón CP enriquecido y tablas auxiliares. |

---

## Orden de migración

El orden es obligatorio por las claves foráneas. No puede alterarse.

1. `personas`
2. `padron_cd`
3. `padron_cp`
4. `referentes`
5. `partidos`
6. `trabajos`
7. `referentes_graduado`
8. `persona_partido`
9. `persona_trabajo`
10. `participacion_electoral`

---

## Detalle por tabla

---

### `personas`

**Fuente:** unión de `fiscaliz_fiscalizar.padroncd24` y `fiscaliz_fiscalizar.padroncp`.

**Criterio:** un registro por DNI. En caso de DNI compartido entre padrones, los datos del nombre provienen de `padroncd24`.

**Problema encontrado:** ambas tablas de origen tenían DNIs duplicados internamente.

**Resolución:** se usó `MIN(id)` para quedarse con el registro más antiguo de cada DNI duplicado.

**Duplicados en `padroncd24`:**

| DNI | Registros | Decisión |
|---|---|---|
| 23888630 | BOHARDT MARA / BORCHARDT MARA | Error tipográfico. Se conservó MIN(id). |
| 33115793 | SALAZAR FRANCISCO JOSE / SANCHEZ TOMAS GONZALO | DNI asignado por error a dos personas distintas. Se conservó MIN(id). |
| 34343620 | D'AZZOGA GALA NEREA / D`AZZOGA GALA NERA | Error de caracter especial. Se conservó MIN(id). |
| 38446070 | CAFERATTA COLOMBRES / CAFFERATA COLOMBRES JUAN MANUEL | Error tipográfico. Se conservó MIN(id). |
| 39466499 | CAMINITI LETICIA / FALAK AGUSTINA | DNI asignado por error a dos personas distintas. Se conservó MIN(id). |
| 39844480 | MARUSOSA / MURUJO SALUZ CAMILA | Error tipográfico. Se conservó MIN(id). |
| 40654627 | NUÑELL / NUÑEZ TOMAS CARLOS | Error tipográfico. Se conservó MIN(id). |

**Nota:** los casos 33115793 y 39466499 son dos personas distintas con el mismo DNI. Requieren verificación manual antes del pase a producción.

**Resultado:** 19.709 registros.

---

### `padron_cd`

**Fuente:** `fiscaliz_fiscalizar.padroncd24`.

**Criterio:** misma lógica de `MIN(id)` para consistencia con `personas`.

**Resultado:** 19.521 registros.

---

### `padron_cp`

**Fuente:** `fiscaliz_fiscalizar.padroncp`.

**Problema encontrado:** 191 DNIs de CP no estaban en `personas` (auxiliares y otros no presentes en CD). Se insertaron primero en `personas` y luego en `padron_cp`.

**Duplicados en `padroncp`:** se encontraron duplicados internos. Se aplicó el mismo criterio `MIN(id)`.

**Campo `auxiliar`:** en la base de origen era varchar SI/NO. Se convirtió a TINYINT(1) con CASE WHEN.

**Resultado:** 4.554 registros.

---

### `referentes`

**Fuente:** `fiscaliz_fiscalizar.responsable`.

**Problema encontrado:** en la base de origen el nombre completo estaba en un solo campo `nombreapellido` con formato NOMBRE APELLIDO.

**Resolución:** se separó usando `SUBSTRING_INDEX` con la regla acordada: todo lo que está después del último espacio es apellido, todo lo anterior es nombre. Esta regla puede generar apellidos compuestos incorrectamente separados (ej: LO PRESTI queda como apellido PRESTI, nombre ESTEBAN LO). Se deja para normalización manual posterior.

**Mapeo de IDs:** los IDs de la tabla origen no coinciden con los IDs de la nueva tabla porque `referentes` reserva el id=1 para SIN REFERENTE. Se creó una tabla temporal `mapeo_referentes` para traducir IDs viejos a nuevos. Esta tabla puede eliminarse una vez validados los datos en producción.

**Resultado:** 270 registros (269 migrados + 1 SIN REFERENTE inicial).

---

### `partidos`

**Fuente:** `fiscaliz_fiscalizar.partido`.

**Campo de origen:** `des` (descripción).

**Mapeo de IDs:** los IDs de origen empiezan en 10001. El join para poblar `persona_partido` se hizo por nombre, no por ID, para evitar errores de mapeo.

**Resultado:** 54 registros (53 migrados + 1 SIN ESPACIO POLITICO inicial).

---

### `trabajos`

**Fuente:** `fiscaliz_fiscalizar.trabajo`.

**Campo de origen:** `des` (descripción).

**Misma lógica que `partidos`.**

**Resultado:** 76 registros (75 migrados + 1 SIN DATO inicial).

---

### `referentes_graduado`

**Fuente:** `fiscaliz_graduados.padroncp2024of` (graduados de CP) y `fiscaliz_fiscalizar.padroncd24` (graduados de CD).

**Criterio:** se insertaron primero los de CP usando `mapeo_referentes` para traducir los IDs. Luego se insertaron los de CD que no tenían fila aún.

**Resultado:** 19.709 registros. Uno por cada persona en `personas`.

---

### `persona_partido`

**Fuente:** `fiscaliz_fiscalizar.padroncd24` joineado con `fiscaliz_fiscalizar.partido` y `fiscaliz_padron.partidos` por nombre.

**Criterio:** no se insertaron los SIN ESPACIO POLITICO (id_partido = 10048 en origen). Quien no tiene partido no tiene fila en esta tabla.

**Resultado:** 1.371 registros.

---

### `persona_trabajo`

**Fuente:** `fiscaliz_fiscalizar.padroncd24` joineado con `fiscaliz_fiscalizar.trabajo` y `fiscaliz_padron.trabajos` por nombre.

**Criterio:** no se insertaron los SIN DATO (id_trabajo = 20090 en origen). Quien no tiene trabajo no tiene fila en esta tabla.

**Resultado:** 2.150 registros.

---

### `participacion_electoral`

**Fuente y criterio por elección:**

| Elección | id | Fuente | Campo origen |
|---|---|---|---|
| CP 2017 | 1 | `fiscaliz_graduados.padroncp2024of` | `voto17 = 'SI'` |
| CP 2019 | 2 | `fiscaliz_graduados.padroncp2024of` | `voto19 = 'SI'` |
| CD 2021 | 3 | `fiscaliz_fiscalizar.padroncd24` | `voto21 = 'SI'` |
| CP 2021 | 4 | `fiscaliz_graduados.padroncp2024of` | `voto21 = 'SI'` |
| CD 2024 | 5 | `fiscaliz_fiscalizar.votos` | `id_tipovoto = 1` |
| CP 2024 | 6 | `fiscaliz_fiscalizar.votoscp` | `id_tipovoto = 1` |

**Nota sobre CD 2024 y CP 2024:** los datos no venían como campo en el padrón sino en tablas de votos donde el vínculo era `id_padron → id` de la tabla de padrón. Se joineó para obtener el DNI.

**Resultado:** 11.965 registros.

---

## Tabla temporal creada durante la migración

| Tabla | Rol | Estado |
|---|---|---|
| `mapeo_referentes` | Traduce IDs de `responsable` viejo a IDs de `referentes` nuevo. | Puede eliminarse después de validar en producción. |

---

## Pendientes antes del pase a producción

- Verificar manualmente los DNIs 33115793 y 39466499 (dos personas distintas con el mismo DNI).
- Normalizar tabla `referentes`: revisar apellidos compuestos mal separados.
- Validación profunda de consistencia general de los datos migrados.
- Eliminar tabla temporal `mapeo_referentes`.

---

## Resumen

Se migraron datos desde dos bases anteriores a `fiscaliz_padron` respetando el orden de claves foráneas. Los principales problemas encontrados fueron DNIs duplicados dentro de los padrones de origen (resueltos con criterio MIN(id)) y la separación de nombre/apellido en la tabla de referentes (resuelta con SUBSTRING_INDEX con regla acordada). Los datos migrados son válidos para desarrollo. La validación profunda se realiza antes del pase a producción.
