# Análisis de la base de datos actual

**Proyecto:** Fiscalizar  
**Fecha:** Febrero 2026  
**Etapa:** Paso 1 — Relevamiento y diagnóstico  

---

## 1. Inventario de tablas

La base de datos actual `fiscaliz_fiscalizar` contiene ocho tablas nucleares agrupadas en tres categorías. Existen tablas adicionales (mesas, elecciones, usuarios) pero fueron escritas y modificadas manualmente para cada elección, por lo que no se consideran parte del diseño estable.

| Categoría | Tablas |
|---|---|
| Catálogos | `carreras`, `responsable`, `partido`, `trabajo` |
| Padrones | `padroncd24`, `padroncp` |
| Votos | `votos`, `votoscp` |

---

## 2. Tablas de catálogo

### `carreras`
Las 5 carreras de la Facultad de Ciencias Sociales: Sociología (CS), Ciencia Política (CP), Relaciones del Trabajo (RT), Trabajo Social (TS) y Ciencias de la Comunicación (CC).

Es la tabla mejor diseñada de toda la base: PK declarada, AUTO_INCREMENT, engine InnoDB y charset utf8. No requiere cambios estructurales, solo migración al nuevo esquema.

### `responsable`
Lista de referentes políticos (~279 registros). Campos: id, nombre (`des`), y dos flags `cd` y `cp` que indican si el referente aplica al padrón de Ciencias Sociales o Ciencia Política respectivamente.

Problemas:
- Nombre de columna `des` no descriptivo.
- Apellido y nombre del referente en un solo campo, sin separación.
- Flags implementados como `varchar(6)` con valores SI/NO en lugar de booleanos.
- Registros con ambos flags en NO funcionan como baja lógica, pero no hay un campo `activo` explícito.
- Engine MyISAM: sin soporte de claves foráneas ni transacciones.

### `partido`
Espacios políticos (~53 registros). Misma estructura y mismos problemas que `responsable`. Engine MyISAM.

### `trabajo`
Lugares de trabajo (~90 registros). Misma estructura que las anteriores. Incluye entradas como `DOCENTES`, `NO DOCENTES` y `SIN DATO` que son categorías de clasificación con valor para el sistema, no solo empleadores reales. Engine MyISAM.

---

## 3. Tablas de padrón

### Contexto

La Facultad de Ciencias Sociales realiza dos procesos electorales distintos:

- **Elección de Consejo Directivo (CD):** habilita a graduados de todas las carreras de la facultad.
- **Elección de Ciencia Política (CP):** habilita a graduados de esa carrera y a docentes auxiliares de la misma, que pueden ser graduados de otras facultades o de otras carreras de Sociales.

Los padrones no son subconjuntos uno del otro. Una persona puede estar en uno, en el otro, o en ambos por razones independientes. Esta distinción es fundamental para el nuevo diseño.

El padrón es acumulativo: nunca se da de baja a un graduado, solo se suman nuevos habilitados con cada elección. Hoy el padrón de CD supera los 20.000 registros, de los cuales aproximadamente 6.000 votan en cada elección.

Ambos padrones se obtienen de la facultad, se tunean y se suben a la base. Se mantienen puros, con todos sus campos originales, tal como los entrega la fuente oficial.

### `padroncd24`
Padrón de graduados habilitados para votar en Consejo Directivo, elección 2024. Campos: id interno, número de orden, apellido, nombre, sigla de carrera, DNI, id_carrera, tres campos de referente (`id_responsable1`, `id_responsable2`, `id_responsable3`), partido político, voto histórico 2021 (`voto21`), lugar de trabajo, sede laboral (texto libre) y comuna/municipio (texto libre). Engine MyISAM, charset latin1.

Problemas:
- Los tres campos de referente son columnas fijas. Cuando una persona no tiene referente, los tres campos se llenan con el id del registro SIN REFERENTE, lo que no distingue "tiene un referente" de "tiene tres entradas vacías".
- El historial de votos está embebido como columna en el padrón (`voto21`). Agregar una elección nueva significa agregar una columna nueva a la tabla.
- `sedelaboral` y `comuna_municipio` son texto libre sin normalizar. En el nuevo diseño estos datos provienen de tablas dedicadas cruzadas por DNI.
- El campo `sigla` duplica información ya presente en `carreras`.
- Engine MyISAM sin integridad referencial. Charset latin1 inconsistente con el resto de la base.

### `padroncp`
Padrón de graduados habilitados para votar en Ciencia Política. Estructura similar a `padroncd24` con historial de tres elecciones embebido: `voto17`, `voto19`, `voto21`. Agrega `id_padron` (número de orden en el padrón oficial externo, en desuso) y `auxiliar` (categoría docente, funciona como booleano pero implementado como varchar). Engine MyISAM, charset latin1.

Problemas: los mismos que `padroncd24`. La acumulación de columnas `voto17`, `voto19`, `voto21` confirma el patrón insostenible: cada elección nueva exige modificar la estructura de la tabla.

---

## 4. Tablas de votos

### `votos` y `votoscp`
Registro de la elección 2024 en tiempo real. Estructura idéntica en ambas: id, fecha, hora, id de mesa, id de registro en el padrón, tipo de voto y usuario/fiscal que registró el voto. Engine InnoDB, charset utf8.

Problemas:
- No existe una tabla de elecciones. El proceso electoral se deduce por las fechas de los registros.
- `id_mesas` e `id_usuarios` referencian tablas que fueron creadas y modificadas manualmente para la elección 2024 y no forman parte del diseño estable.
- `id_padron` referencia el id del padrón correspondiente sin clave foránea declarada. En el nuevo diseño el DNI reemplaza a este campo como clave de cruce.
- Tener dos tablas paralelas (`votos` y `votoscp`) replica la misma lógica para cada proceso electoral. El nuevo diseño unifica esto.
- El dato relevante a preservar es si el graduado votó y en qué fecha. No es necesario guardar la mesa ni el fiscal en el registro histórico.

---

## 5. Modelo de presentación — situación actual

No existe en la base actual ningún mecanismo que defina qué campos mostrar al consultar un graduado. Esa lógica está hardcodeada en el PHP: el código sabe exactamente qué tablas consultar y qué columnas mostrar. Cualquier cambio en los datos a presentar requiere modificar el código.

El nuevo diseño traslada esa responsabilidad a vistas SQL predefinidas. El PHP hace SELECT contra las vistas y presenta lo que encuentra, de forma dinámica. Agregar una tabla nueva o un campo nuevo es una operación sobre la vista. El PHP no se toca.

Todo listado visible por pantalla debe poder exportarse a Excel. Las vistas se diseñan planas y limpias para que esa exportación sea directa.

---

## 6. Gestión de tablas — situación actual

No hay distinción formal entre tablas administradas por el sistema y tablas incorporadas desde fuentes externas. En el nuevo diseño todas las tablas se tratan igual: el administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI. Esta uniformidad simplifica el mantenimiento y elimina la necesidad de categorizar las fuentes.

---

## 7. Problemas transversales

**Inconsistencia de engines:** MyISAM en catálogos y padrones, InnoDB en votos. MyISAM no soporta claves foráneas ni transacciones. Toda la integridad referencial depende del código PHP.

**Inconsistencia de charsets:** latin1 en la mayoría de las tablas, utf8 en `carreras` y tablas de votos. Puede generar problemas de codificación al cruzar datos entre tablas.

**Ausencia de integridad referencial:** ninguna relación entre tablas está declarada como clave foránea. Los vínculos existen en los datos pero no están garantizados por el motor.

**Nombres de columnas no descriptivos:** `des` para descripción, `cd` y `cp` para flags. Requieren conocimiento previo del sistema para interpretarse.

**Referentes sin separación de apellido y nombre:** el campo `des` en `responsable` contiene apellido y nombre juntos, lo que impide ordenar o filtrar por apellido.

**Historial electoral embebido en el padrón:** los campos `voto17`, `voto19`, `voto21` dentro de la tabla de padrón acoplan el esquema al calendario electoral. En el nuevo diseño el historial de participación es una tabla separada.

**Tablas de fiscalización frágiles:** las tablas de mesas, elecciones y usuarios fueron creadas y modificadas manualmente para cada elección. En el nuevo diseño el módulo de fiscalización será un conjunto de tablas reutilizables elección a elección.

---

## 8. Decisiones de diseño acordadas para el nuevo esquema

Las siguientes decisiones surgen del análisis y quedan registradas aquí como punto de partida para el Paso 2:

1. **DNI como clave única de cruce** en toda la base. Es el nexo entre todas las tablas.
2. **Tabla `personas`:** DNI, apellido y nombre. Un registro por DNI, sin duplicados entre padrones. Nunca se elimina un registro. Es el núcleo del esquema.
3. **`padron_cd` y `padron_cp` se mantienen puros:** se cargan tal como los entrega la facultad, con todos sus campos originales. No son subconjuntos uno del otro.
4. **Tabla `referentes_graduado`:** vincula cada DNI con hasta 3 referentes mediante tres columnas fijas (`referente_1`, `referente_2`, `referente_3`). El límite de 3 es firme e histórico.
5. **Referentes con apellido y nombre separados** en la tabla `referentes`.
6. **Tabla de participación electoral separada:** registra por DNI y elección si el graduado votó y en qué fecha. Reemplaza las columnas `voto17`, `voto19`, `voto21` embebidas en el padrón.
7. **Tabla `elecciones`:** catálogo de procesos electorales con identidad propia. Permite registrar elecciones pasadas y futuras.
8. **Vistas como interfaz para el PHP:** `vista_padron_cd` y `vista_padron_cp` joinean todas las tablas por DNI. El PHP solo hace SELECT contra las vistas. La exportación a Excel se construye dinámicamente desde el resultado de la vista.
9. **Todas las tablas se administran igual:** no hay distinción entre tablas internas y externas. Toda tabla nueva requiere DNI, apellido y nombre como campos obligatorios.
10. **Todo InnoDB, todo utf8mb4:** consistencia de engine y charset en toda la base nueva.
11. **Sistema de login para Consulta Padrón** con niveles de acceso diferenciados. Se diseña en la etapa de desarrollo.
12. **Sistema de login para Fiscalización** independiente del anterior. Se diseña en esa etapa.
13. **Módulo de fiscalización para etapa futura:** las tablas de mesas, fiscales y registro en tiempo real se diseñarán en la siguiente etapa sin modificar las tablas existentes.

---

## Resumen

La base actual es funcional pero creció sin diseño previo, acumulando deuda técnica en cinco frentes: ausencia de integridad referencial por uso de MyISAM, modelo de referentes con columnas fijas que no distingue ausencia de presencia, historial electoral embebido en el padrón que crece con cada elección, lógica de presentación hardcodeada en el PHP sin posibilidad de adaptarse a nuevas fuentes de datos, y tablas de fiscalización creadas manualmente para cada proceso electoral. El nuevo diseño resuelve estos cinco problemas, mantiene los padrones puros tal como los entrega la facultad, centraliza los individuos en una tabla `personas` joineada por DNI, y establece una arquitectura que puede escalar desde Consulta Padrón hasta Fiscalización sin cambios estructurales.
