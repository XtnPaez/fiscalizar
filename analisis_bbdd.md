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
- Flags implementados como `varchar(6)` con valores SI/NO en lugar de booleanos.
- Registros con ambos flags en NO funcionan como baja lógica, pero no hay un campo `activo` explícito.
- Engine MyISAM: sin soporte de claves foráneas ni transacciones.

### `partido`
Espacios políticos (~53 registros). Misma estructura y mismos problemas que `responsable`. Engine MyISAM.

### `trabajo`
Lugares de trabajo (~90 registros). Misma estructura que las anteriores. Incluye entradas como `AUTORIDADES`, `DOCENTES`, `NO DOCENTES` y `SIN DATO` que son categorías administrativas, no empleadores reales. La tabla mezcla dos conceptos distintos. Engine MyISAM.

---

## 3. Tablas de padrón

### Contexto

La Facultad de Ciencias Sociales realiza dos procesos electorales distintos:

- **Elección de Consejo Directivo (CD):** habilita a graduados de todas las carreras de la facultad.
- **Elección de Ciencia Política (CP):** habilita a graduados de esa carrera y a docentes auxiliares de la misma, que pueden ser graduados de otras facultades o de otras carreras de Sociales.

Los padrones no son subconjuntos uno del otro. Una persona puede estar en uno, en el otro, o en ambos por razones independientes. Esta distinción es fundamental para el nuevo diseño.

El padrón es acumulativo: nunca se da de baja a un graduado, solo se suman nuevos habilitados con cada elección. Hoy el padrón de CD supera los 20.000 registros, de los cuales aproximadamente 6.000 votan en cada elección.

### `padroncd24`
Padrón de graduados habilitados para votar en Consejo Directivo, elección 2024. Campos: id interno, número de orden, apellido, nombre, sigla de carrera, DNI, id_carrera, tres campos de referente (`id_responsable1`, `id_responsable2`, `id_responsable3`), partido político, voto histórico 2021 (`voto21`), lugar de trabajo, sede laboral (texto libre) y comuna/municipio (texto libre). Engine MyISAM, charset latin1.

Problemas:
- Los tres campos de referente son columnas fijas. Cuando una persona no tiene referente, los tres campos se llenan con el id del registro SIN REFERENTE, lo que no distingue "tiene un referente" de "tiene tres entradas vacías".
- El historial de votos está embebido como columna en el padrón (`voto21`). Agregar una elección nueva significa agregar una columna nueva a la tabla, lo que hace el esquema dependiente del calendario electoral.
- `sedelaboral` y `comuna_municipio` son texto libre sin normalizar. En el nuevo diseño estos datos provienen de listados externos cruzados por DNI.
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

El nuevo diseño incorpora una tabla de metadatos llamada `catalogo` que invierte esa responsabilidad. El PHP consulta el catálogo para saber qué mostrar, y presenta lo que la base le indique. El catálogo define por cada campo: de qué tabla proviene, cómo se llama la columna, en qué orden mostrarlo, y si aplica al padrón CD, al padrón CP, o a ambos (flags booleanos `cd` y `cp`).

Agregar un campo nuevo, incorporar un listado externo o modificar lo que se muestra es una operación exclusivamente sobre la base de datos. El PHP no se toca.

Todo listado visible por pantalla debe poder exportarse a Excel. Las vistas de consulta se diseñan planas y limpias para que esa exportación sea directa.

---

## 6. Listados externos — situación actual

No existe soporte para listados externos. La base actual no tiene mecanismo para incorporar fuentes de datos adicionales y cruzarlas con los padrones.

El nuevo diseño incorpora este soporte desde el origen bajo las siguientes reglas:

- Los listados se suben a la base tuneados por el administrador antes de la carga. No se suben crudos.
- El DNI es el campo obligatorio de cruce. Sin DNI no hay match posible.
- Los listados se suben completos, no solo los registros que matchean con el padrón vigente. Esto es porque el padrón crece elección a elección y un registro que hoy no matchea puede matchear en el futuro cuando ese graduado sea incorporado.
- En el catálogo se registran los campos del listado que deben mostrarse.
- El PHP no distingue entre un campo del padrón y un campo de un listado externo: consulta el catálogo y presenta lo que encuentra.

Ejemplos de listados externos ya identificados: sede laboral, comuna/municipio. Ejemplos de posibles listados futuros: afiliados a un sindicato, miembros de un colegio profesional.

---

## 7. Problemas transversales

**Inconsistencia de engines:** MyISAM en catálogos y padrones, InnoDB en votos. MyISAM no soporta claves foráneas ni transacciones. Toda la integridad referencial depende del código PHP.

**Inconsistencia de charsets:** latin1 en la mayoría de las tablas, utf8 en `carreras` y tablas de votos. Puede generar problemas de codificación al cruzar datos entre tablas.

**Ausencia de integridad referencial:** ninguna relación entre tablas está declarada como clave foránea. Los vínculos existen en los datos pero no están garantizados por el motor.

**Nombres de columnas no descriptivos:** `des` para descripción, `cd` y `cp` para flags. Requieren conocimiento previo del sistema para interpretarse.

**Mezcla de conceptos en `trabajo`:** combina empleadores reales con categorías administrativas como DOCENTES o SIN DATO.

**Historial electoral embebido en el padrón:** los campos `voto17`, `voto19`, `voto21` dentro de la tabla de padrón acoplan el esquema al calendario electoral. En el nuevo diseño el historial de participación es una tabla separada.

**Tablas de fiscalización frágiles:** las tablas de mesas, elecciones y usuarios fueron creadas y modificadas manualmente para cada elección. En el nuevo diseño el módulo de fiscalización será un conjunto de tablas reutilizables elección a elección.

---

## 8. Decisiones de diseño acordadas para el nuevo esquema

Las siguientes decisiones surgen del análisis y quedan registradas aquí como punto de partida para el Paso 2:

1. **DNI como clave única de cruce** en toda la base. Reemplaza cualquier id interno como nexo entre tablas.
2. **Tabla `personas` acumulativa:** registro maestro de individuos. Nunca se elimina un registro. Los padrones suman graduados nuevos con cada elección.
3. **`padron_cd` y `padron_cp` como tablas independientes:** cada una contiene los DNIs habilitados para su proceso electoral. No son subconjuntos uno del otro.
4. **Tabla de participación electoral separada:** registra por DNI, elección y padrón si el graduado votó y en qué fecha. Reemplaza las columnas `voto17`, `voto19`, `voto21` embebidas en el padrón.
5. **Referentes como tabla de relación:** hasta 3 referentes por persona, almacenados como filas en una tabla separada, no como columnas fijas. Quien no tiene referente no tiene filas en esa tabla.
6. **Tabla `catalogo`** con flags `cd` y `cp`: define qué campos mostrar para cada padrón. Incluye campos del padrón, de catálogos y de listados externos. El PHP consulta esta tabla para armar la presentación.
7. **Listados externos con DNI obligatorio:** se suben tuneados y completos. Sus campos a mostrar se registran en `catalogo`.
8. **Todo InnoDB, todo utf8mb4:** consistencia de engine y charset en toda la base nueva.
9. **Módulo de fiscalización para etapa futura:** las tablas de mesas, fiscales y registro en tiempo real se diseñarán en la siguiente etapa. No se replican en este diseño.
10. **Exportación a Excel** contemplada desde el diseño: las vistas de consulta son planas y aptas para exportación directa.

---

## Resumen

La base actual es funcional pero creció sin diseño previo, acumulando deuda técnica en cinco frentes: ausencia de integridad referencial por uso de MyISAM, modelo de referentes con columnas fijas que no distingue ausencia de presencia, historial electoral embebido en el padrón que crece con cada elección, lógica de presentación hardcodeada en el PHP sin posibilidad de adaptarse a nuevas fuentes de datos, y tablas de fiscalización creadas manualmente para cada proceso electoral. El nuevo diseño resuelve estos cinco problemas, mantiene compatibilidad con los datos existentes para que la migración sea posible sin pérdida de información, y establece una arquitectura que puede escalar desde Consulta Padrón hasta Fiscalización sin cambios estructurales.
