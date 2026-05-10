# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar  
**Fecha:** Mayo 2026 (actualizado)  
**Etapa:** Diseño del esquema — version activa  

---

## 1. Principios que guían este diseño

- **DNI como clave única de cruce** entre todas las tablas.
- **Todo InnoDB, todo utf8mb4:** integridad referencial garantizada por el motor, no por el código.
- **La lógica vive en las vistas:** el PHP hace SELECT contra vistas predefinidas y presenta lo que encuentra. No decide qué tablas cruzar ni qué campos mostrar.
- **Los padrones se mantienen puros:** se cargan tal como los entrega la facultad, con todos sus campos originales.
- **Padrones acumulativos:** nunca se elimina un registro. Solo se suman nuevos habilitados con cada elección.
- **Todas las tablas se administran igual:** el administrador las obtiene, las tunea y las sube. El sistema las consume joineando por DNI.
- **Escalabilidad hacia Fiscalización:** el esquema no necesita cambios estructurales para incorporar el módulo electoral en etapas futuras.

---

## 2. Mapa general de tablas

```
NUCLEO
    personas

PADRONES (puros, tal como los entrega la facultad)
    padron_cd       — graduados habilitados para CD (todas las carreras)
    padron_cp       — graduados y auxiliares habilitados para CP
    padron_rt       — graduados y auxiliares habilitados para RT
    padron_cs       — graduados y auxiliares habilitados para CS (Sociologia)

CATALOGOS
    carreras
    referentes
    partidos
    trabajos
    sedes
    municipios

RELACIONES
    auxiliares              (DNI <-> carrera donde es auxiliar, many-to-many)
    referentes_graduado     (DNI <-> hasta 3 referentes, limite firme e historico)
    persona_partido         (DNI <-> espacio politico)
    persona_trabajo         (DNI <-> lugar de trabajo)
    persona_sede            (DNI <-> sede laboral)
    persona_municipio       (DNI <-> municipio o comuna)
    elecciones              (catalogo de procesos electorales: cd, cp, rt, cs)
    participacion_electoral (historial: solo se registran los que votaron)

AUTENTICACION
    usuarios                (usuarios del modulo Consulta Padron)

TABLAS ADICIONALES (cruce por DNI via LEFT JOIN)
    st_siet_2026
    st_ucr_caba_2026
    st_ucr_pba_2024

TABLAS STAGING (prefijo st_, fuente de migraciones y actualizaciones)
    st_carreras
    st_referentes
    st_partidos
    st_trabajo
    st_padron_cd_datos
    st_padron_cp_datos
    st_auxiliares_cp
    st_votos_cd_24
    st_votos_cp_24
    st_padron_cd_2026
    st_padron_cp_2026
    st_padron_rt_2026
    st_padron_cs_2026
    st_padron_profesores_cp_2026
    st_actualizacion_referentes_2026
    st_bajas_padron_cd_2026
    st_bajas_padron_cp_2026
    st_padron_cd_2024           (backup pre-actualizacion)
    st_padron_cp_2024           (backup pre-actualizacion)
    st_padron_cp_pre_auxiliares_2026 (backup pre-marcado de auxiliares)
    st_persona_partido_pre_actualizacion_2026 (backup pre-actualizacion)

TABLAS FISCALIZACION (modulo electoral — pendiente)
    mesas
    usuarios_fiscal
    votos_dia

VISTAS
    vista_padron_cd
    vista_padron_cp
    (vista_padron_rt y vista_padron_cp se crean en la etapa Fiscalizacion)
```

---

## 3. Descripcion de cada tabla productiva

---

### `personas`
**Rol:** tabla nucleo del esquema. Contiene un registro unico por DNI, sin duplicados entre padrones. Es el punto de joineo de todas las tablas. Nunca se elimina un registro.

Un DNI aparece aqui si esta en cualquiera de los cuatro padrones. Si alguien esta en varios padrones, en `personas` aparece una sola vez.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria. |
| `apellido` | VARCHAR(120) | Mayusculas, sin tildes, con N. |
| `nombre` | VARCHAR(120) | Mayusculas, sin tildes, con N. |

---

### `padron_cd`
**Rol:** padron oficial de Consejo Directivo tal como lo publica la facultad. Contiene graduados de todas las carreras. Acumulativo: solo se agregan registros, nunca se eliminan. A partir de 2026 el padron oficial ya no incluye la sigla de carrera — ese campo queda NULL para los registros nuevos.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `sigla` | VARCHAR(12) | Sigla de carrera. NULL desde el padron 2026. |

---

### `padron_cp`
**Rol:** padron oficial de Ciencia Politica tal como lo publica la facultad. Incluye graduados de CP y docentes auxiliares mezclados. El campo `auxiliar` se marca durante el tuneo: 1 si el DNI no figura en `padron_cd`.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `auxiliar` | TINYINT(1) | 1 = docente auxiliar, 0 = graduado. |

---

### `padron_rt`
**Rol:** padron oficial de Relaciones del Trabajo tal como lo publica la facultad. Incluye graduados de RT y docentes auxiliares mezclados. Los auxiliares se identifican cruzando contra `padron_cd` y se registran en la tabla `auxiliares`. Solo se usa en el modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |

---

### `padron_cs`
**Rol:** padron oficial de Sociologia tal como lo publica la facultad. Misma logica que `padron_rt`. Solo se usa en el modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |

---

### `auxiliares`
**Rol:** registra que DNIs son auxiliares docentes y en que carrera. Un DNI puede ser auxiliar en mas de una carrera simultaneamente (clave primaria compuesta). Se puebla cruzando cada padron de carrera contra `padron_cd`: quien no esta en CD es auxiliar.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | PK compuesta. Foranea a `personas`. |
| `id_carrera` | INT | PK compuesta. Foranea a `carreras`. |

**Estado actual:**

| Carrera | id_carrera | Auxiliares |
|---|---|---|
| Sociologia (CS) | 1 | 161 |
| Ciencia Politica (CP) | 2 | 175 |
| Relaciones del Trabajo (RT) | 3 | 118 |
| **Total** | | **454** |

---

### `carreras`
**Rol:** catalogo cerrado de carreras de la facultad. Sin AUTO_INCREMENT: los ids tienen significado propio. id 1-5 para las carreras reales, id 99 reservado para SIN DATO.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. Sin AUTO_INCREMENT. |
| `descripcion` | VARCHAR(50) | Nombre completo de la carrera. |
| `sigla` | VARCHAR(5) | Sigla. Ej: CP, CS, RT, TS, CC, SD. |

| id | descripcion | sigla |
|---|---|---|
| 1 | Sociologia | CS |
| 2 | Ciencia Politica | CP |
| 3 | Relaciones del Trabajo | RT |
| 4 | Trabajo Social | TS |
| 5 | Ciencias de la Comunicacion | CC |
| 99 | Sin dato | SD |

---

### `referentes`
**Rol:** catalogo de referentes politicos. Apellido y nombre en campos separados. Baja logica via campo `activo`. Nunca se elimina un registro.

El id 250 esta reservado para SIN REFERENTE. Toda posicion de referente vacia en `referentes_graduado` apunta a este id en lugar de NULL.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `apellido` | VARCHAR(80) | Apellido del referente. |
| `nombre` | VARCHAR(80) | Nombre del referente. S/N si no tiene nombre. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `partidos`
**Rol:** catalogo de espacios politicos. Baja logica via campo `activo`. Nunca se elimina un registro.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(80) | Nombre del espacio politico. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `trabajos`
**Rol:** catalogo de lugares de trabajo. Incluye categorias administrativas como DOCENTE y NO DOCENTE. Baja logica via campo `activo`. Nunca se elimina un registro.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre del lugar de trabajo o categoria. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `sedes`
**Rol:** catalogo de sedes laborales. 50 valores normalizados. Baja logica via campo `activo`. Nunca se elimina un registro. El id 1 esta reservado para SIN DATO.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre de la sede laboral. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `municipios`
**Rol:** catalogo de municipios y comunas. 84 valores normalizados. Incluye comunas de CABA (COMUNA 1 a 15) y partidos del GBA e interior de PBA. Baja logica via campo `activo`. Nunca se elimina un registro. El id 1 esta reservado para SIN DATO.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre del municipio o comuna. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `referentes_graduado`
**Rol:** vincula cada DNI con hasta 3 referentes. El limite de 3 es firme e historico. Las posiciones vacias apuntan al id 250 (SIN REFERENTE) en lugar de NULL. Toda persona tiene fila en esta tabla.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `referente_1` | INT | Foranea a `referentes`. 250 si no tiene. |
| `referente_2` | INT | Foranea a `referentes`. 250 si no tiene. |
| `referente_3` | INT | Foranea a `referentes`. 250 si no tiene. |

---

### `persona_partido`
**Rol:** vincula cada DNI con su espacio politico. Un partido por persona. Se actualiza via ABM del sistema o desde staging.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_partido` | INT | Foranea a `partidos`. |

---

### `persona_trabajo`
**Rol:** vincula cada DNI con su lugar de trabajo. Un trabajo por persona. Se actualiza via ABM del sistema o desde staging.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_trabajo` | INT | Foranea a `trabajos`. |

---

### `persona_sede`
**Rol:** vincula cada DNI con su sede laboral. Una sede por persona. Toda persona tiene fila con al menos SIN DATO (id=1).

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_sede` | INT | Foranea a `sedes`. |

---

### `persona_municipio`
**Rol:** vincula cada DNI con su municipio o comuna. Un municipio por persona. Toda persona tiene fila con al menos SIN DATO (id=1).

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_municipio` | INT | Foranea a `municipios`. |

---

### `elecciones`
**Rol:** catalogo de procesos electorales pasados y futuros. Solo una eleccion puede estar activa por tipo en simultaneo. El ENUM incluye los cuatro tipos de proceso electoral de la facultad.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(80) | Ej: Eleccion CD 2024. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de proceso electoral. |
| `anio` | YEAR | Año de la eleccion. |
| `activa` | TINYINT(1) | 1 = eleccion en curso. |

**Elecciones cargadas:**

| id | nombre | tipo | anio |
|---|---|---|---|
| 1 | Eleccion CP 2017 | cp | 2017 |
| 2 | Eleccion CP 2019 | cp | 2019 |
| 3 | Eleccion CD 2021 | cd | 2021 |
| 4 | Eleccion CP 2021 | cp | 2021 |
| 5 | Eleccion CD 2024 | cd | 2024 |
| 6 | Eleccion CP 2024 | cp | 2024 |
| 7 | Eleccion RT 2026 | rt | 2026 |
| 8 | Eleccion CS 2026 | cs | 2026 |

---

### `participacion_electoral`
**Rol:** historial de participacion. Solo se registran los que votaron. Agregar una eleccion nueva es insertar filas aqui, no modificar el esquema.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Foranea a `personas`. |
| `id_eleccion` | INT | Foranea a `elecciones`. |
| `fecha_registro` | DATE | Fecha en que se registro el voto. NULL en registros historicos. |

---

### `usuarios`
**Rol:** usuarios del modulo Consulta Padron. Login independiente del modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Nombre de usuario. Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('consulta','admin','superadmin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `mesas` (modulo Fiscalizacion — pendiente)
**Rol:** mesas electorales del dia de la eleccion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(60) | Ej: LU CP M1, MA CD M3. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de padron que atiende. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `habilitada` | TINYINT(1) | 1 = aparece en combo login. |
| `en_uso` | TINYINT(1) | 1 = hay sesion activa. |
| `activa` | TINYINT(1) | 1 = puede recibir votos. |

---

### `usuarios_fiscal` (modulo Fiscalizacion — pendiente)
**Rol:** usuarios admin y superadmin del modulo Fiscalizacion.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Nombre de usuario. Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('superadmin','admin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `votos_dia` (modulo Fiscalizacion — pendiente)
**Rol:** registro en tiempo real del dia de la eleccion. Al cerrar la eleccion sus registros se migran a `participacion_electoral`.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | DNI del votante. |
| `id_mesa` | INT | FK a mesas. |
| `tipo_voto` | ENUM('regular','observado') | Default regular. |
| `timestamp` | DATETIME | Fecha y hora. Default CURRENT_TIMESTAMP. |

---

## 4. Vistas principales

Las vistas son la unica interfaz entre la base de datos y el PHP. El PHP hace SELECT contra las vistas y nunca consulta las tablas directamente.

### `vista_padron_cd`
Perfil completo de cada habilitado para votar en CD. Cruza por DNI todas las tablas de relacion. Solo muestra referentes con `activo = 1`. Los campos sin dato devuelven texto explicito (SIN REFERENTE, SIN PARTIDO, SIN TRABAJO, SIN SEDE, SIN MUNICIPIO) en lugar de NULL.

### `vista_padron_cp`
Idem para CP. El campo `auxiliar` devuelve SI o NO como texto.

### Vistas pendientes
`vista_padron_rt` y `vista_padron_cs` se crean en la etapa Fiscalizacion. Por ahora `padron_rt` y `padron_cs` existen como tablas pero no tienen vista asociada.

### Notas de diseno de las vistas

- Los JOIN a `referentes` incluyen `AND r.activo = 1`. Un referente dado de baja no aparece en el resultado.
- COALESCE en todos los campos opcionales: devuelven texto explicito en lugar de NULL.
- Agregar una eleccion nueva requiere agregar un LEFT JOIN a `participacion_electoral` y una columna CASE WHEN en el SELECT. El PHP no se modifica.
- Agregar una tabla nueva requiere agregar un LEFT JOIN por DNI. El PHP no se modifica.

---

## 5. Relaciones entre tablas

```
personas ──────────────── padron_cd               (dni)
personas ──────────────── padron_cp               (dni)
personas ──────────────── padron_rt               (dni)
personas ──────────────── padron_cs               (dni)
personas ──────────────── auxiliares              (dni)
personas ──────────────── referentes_graduado     (dni)
personas ──────────────── persona_partido         (dni)
personas ──────────────── persona_trabajo         (dni)
personas ──────────────── persona_sede            (dni)
personas ──────────────── persona_municipio       (dni)
personas ──────────────── participacion_electoral (dni)
personas ──────────────── st_siet_2026            (dni, LEFT JOIN)
personas ──────────────── st_ucr_caba_2026        (dni, LEFT JOIN)
personas ──────────────── st_ucr_pba_2024         (dni, LEFT JOIN)

carreras ──────────────── auxiliares              (id -> id_carrera)
referentes ────────────── referentes_graduado     (id -> referente_1/2/3)
partidos ──────────────── persona_partido         (id -> id_partido)
trabajos ──────────────── persona_trabajo         (id -> id_trabajo)
sedes ─────────────────── persona_sede            (id -> id_sede)
municipios ────────────── persona_municipio       (id -> id_municipio)
elecciones ────────────── participacion_electoral (id -> id_eleccion)
mesas ─────────────────── votos_dia               (id -> id_mesa)
```

---

## 6. Estado de carga de datos

| Tabla | Registros | Estado |
|---|---|---|
| `personas` | 22.325 | ✅ Actualizado mayo 2026 |
| `padron_cd` | 21.745 | ✅ Actualizado mayo 2026 |
| `padron_cp` | 4.843 | ✅ Actualizado mayo 2026 |
| `padron_rt` | 5.240 | ✅ Cargado mayo 2026 |
| `padron_cs` | 4.829 | ✅ Cargado mayo 2026 |
| `auxiliares` | 454 | ✅ Cargado mayo 2026 |
| `carreras` | 6 | ✅ Cargado |
| `referentes` | 324 | ✅ Actualizado mayo 2026 |
| `partidos` | 87 | ✅ Actualizado mayo 2026 |
| `trabajos` | 105 | ✅ Actualizado mayo 2026 |
| `sedes` | 50 | ✅ Cargado |
| `municipios` | 84 | ✅ Cargado |
| `elecciones` | 8 | ✅ Actualizado mayo 2026 |
| `referentes_graduado` | 22.325 | ✅ Actualizado mayo 2026 |
| `persona_partido` | 1.560 | ✅ Actualizado mayo 2026 |
| `persona_trabajo` | 2.150 | ✅ Migrado |
| `persona_sede` | 19.709 | ✅ Cargado |
| `persona_municipio` | 19.709 | ✅ Cargado |
| `participacion_electoral` | 11.974 | ✅ Migrado |
| `usuarios` | 1 | ✅ Superadmin creado |
| `mesas` | 0 | ⏳ Se crea en Fiscalizacion |
| `usuarios_fiscal` | 0 | ⏳ Se crea en Fiscalizacion |
| `votos_dia` | 0 | ⏳ Se puebla el dia de la eleccion |

---

## 7. Lo que este esquema deja preparado para Fiscalizacion

- `elecciones` ya existe con el campo `activa` y el ENUM extendido a cd/cp/rt/cs.
- `participacion_electoral` recibira los registros migrados desde `votos_dia` al cerrar la eleccion.
- `padron_rt` y `padron_cs` estan cargados y listos.
- `auxiliares` identifica por DNI y carrera quienes son auxiliares en cada proceso.
- `mesas`, `usuarios_fiscal` y `votos_dia` se agregan sin modificar las tablas existentes.

---

## 8. Nota de collation

Las tablas productivas usan `utf8mb4_spanish_ci`. Las tablas staging usan `utf8mb4_unicode_ci`. Para evitar el error 1271 al hacer UNION entre vistas de distintas collations, la conexion PDO incluye:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Esta linea es obligatoria en `config/db.php`.

---

## Resumen

El esquema centraliza la identidad de cada individuo en `personas`, mantiene cuatro padrones puros (CD, CP, RT, CS), identifica auxiliares por carrera en una tabla dedicada, separa todos los vinculos en tablas independientes actualizables via ABM o staging, y expone todo el dato a traves de dos vistas que el PHP consulta directamente. El modulo Fiscalizacion agrega tres tablas propias y dos vistas nuevas sin modificar las existentes.
