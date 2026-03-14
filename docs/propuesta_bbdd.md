# Propuesta de nueva base de datos

**Proyecto:** Fiscalizar  
**Fecha:** Marzo 2026  
**Etapa:** Paso 2 — Diseño del nuevo esquema  

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
    padron_cd
    padron_cp

CATALOGOS
    carreras
    referentes
    partidos
    trabajos

RELACIONES
    referentes_graduado     (DNI <-> hasta 3 referentes, limite firme e historico)
    persona_partido         (DNI <-> espacio politico)
    persona_trabajo         (DNI <-> lugar de trabajo)
    elecciones              (catalogo de procesos electorales)
    participacion_electoral (historial: solo se registran los que votaron)

AUTENTICACION
    usuarios                (usuarios del modulo Consulta Padron)

TABLAS ADICIONALES (una por fuente, cruce por DNI via LEFT JOIN)
    sede_laboral
    st_siet_2026
    st_ucr_caba_2026
    st_ucr_pba_2024
    (futuras: sindicato, colegio profesional, afiliacion partidaria, etc.)

TABLAS STAGING (prefijo st_, fuente de la migracion)
    st_carreras
    st_referentes
    st_partidos
    st_trabajo
    st_padron_cd_datos
    st_padron_cp_datos
    st_auxiliares_cp
    st_votos_cd_24
    st_votos_cp_24

VISTAS
    vista_padron_cd
    vista_padron_cp
```

---

## 3. Descripcion de cada tabla productiva

---

### `personas`
**Rol:** tabla nucleo del esquema. Contiene un registro unico por DNI, sin duplicados entre padrones. Es el punto de joineo de todas las tablas. Nunca se elimina un registro.

Un DNI aparece aqui si esta en `padron_cd`, en `padron_cp`, o en ambos. Si alguien esta en los dos padrones, en `personas` aparece una sola vez.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria. |
| `apellido` | VARCHAR(120) | Mayusculas, sin tildes, con N. |
| `nombre` | VARCHAR(120) | Mayusculas, sin tildes, con N. |

---

### `padron_cd`
**Rol:** padron oficial de Consejo Directivo tal como lo publica la facultad. Acumulativo: solo se agregan registros, nunca se eliminan. No contiene DNIs repetidos internamente, pero puede compartir DNIs con `padron_cp`.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `sigla` | VARCHAR(12) | Sigla de la carrera segun el padron oficial. |

---

### `padron_cp`
**Rol:** padron oficial de Ciencia Politica tal como lo publica la facultad. Incluye graduados de CP y docentes auxiliares. El campo `auxiliar` se agrega durante el tuneo previo a la carga.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | BIGINT UNSIGNED | Clave primaria interna. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Clave foranea a `personas`. |
| `apellido` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `nombre` | VARCHAR(120) | Tal como figura en el padron oficial. |
| `auxiliar` | TINYINT(1) | 1 = docente auxiliar, 0 = graduado. |

---

### `carreras`
**Rol:** catalogo cerrado de carreras de la facultad. Sin AUTO_INCREMENT: los ids tienen significado propio. id 1-5 para las carreras reales, id 99 reservado para SIN DATO.

**Nota de diseno:** se eligio id=99 para SIN DATO en lugar de NULL para mantener el catalogo cerrado y ser explicito respecto a los auxiliares de otras facultades que no tienen carrera en Sociales. El id 6 queda reservado para una eventual nueva carrera.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. Sin AUTO_INCREMENT. |
| `descripcion` | VARCHAR(50) | Nombre completo de la carrera. |
| `sigla` | VARCHAR(5) | Sigla. Ej: CP, CS, RT, TS, CC, SD. |

**Datos cargados:**

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

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `apellido` | VARCHAR(80) | Apellido del referente. |
| `nombre` | VARCHAR(80) | Nombre del referente. |
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
**Rol:** catalogo de lugares de trabajo. Incluye categorias administrativas como DOCENTE y NO DOCENTE como valores validos con significado para el sistema. Baja logica via campo `activo`. Nunca se elimina un registro.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(120) | Nombre del lugar de trabajo o categoria. |
| `aplica_cd` | TINYINT(1) | 1 si aplica al padron CD. |
| `aplica_cp` | TINYINT(1) | 1 si aplica al padron CP. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

---

### `referentes_graduado`
**Rol:** vincula cada DNI con hasta 3 referentes. El limite de 3 es firme e historico. NULL indica ausencia de referente en esa posicion. Quien no tiene ningun referente no tiene fila en esta tabla.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `referente_1` | INT | Foranea a `referentes`. NULL si no tiene. |
| `referente_2` | INT | Foranea a `referentes`. NULL si no tiene. |
| `referente_3` | INT | Foranea a `referentes`. NULL si no tiene. |

---

### `persona_partido`
**Rol:** vincula cada DNI con su espacio politico. Un partido por persona. Quien no tiene partido no tiene fila en esta tabla. Se actualiza via ABM del sistema.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_partido` | INT | Foranea a `partidos`. |

---

### `persona_trabajo`
**Rol:** vincula cada DNI con su lugar de trabajo. Un trabajo por persona. Quien no tiene trabajo no tiene fila en esta tabla. Se actualiza via ABM del sistema.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `id_trabajo` | INT | Foranea a `trabajos`. |

---

### `elecciones`
**Rol:** catalogo de procesos electorales pasados y futuros. Solo una eleccion puede estar activa por tipo en simultaneo.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `nombre` | VARCHAR(80) | Ej: Eleccion CD 2024. |
| `tipo` | ENUM('cd','cp') | Tipo de proceso electoral. |
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

---

### `participacion_electoral`
**Rol:** historial de participacion. Solo se registran los que votaron. Quien no figura en esta tabla para una eleccion determinada, no voto. Agregar una eleccion nueva es insertar filas aqui, no modificar el esquema.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | Foranea a `personas`. |
| `id_eleccion` | INT | Foranea a `elecciones`. |
| `fecha_registro` | DATE | Fecha en que se registro el voto. NULL en registros historicos. |

---

### `sede_laboral`
**Rol:** sede laboral por DNI. Texto libre, sin normalizar. Un registro por DNI. Se carga cuando el administrador tenga el listado tuneado. Se cruza por DNI contra `personas` via LEFT JOIN desde las vistas.

| Campo | Tipo | Descripcion |
|---|---|---|
| `dni` | INT UNSIGNED | Clave primaria y foranea a `personas`. |
| `sede` | VARCHAR(200) | Texto libre tal como viene de la fuente. |

---

### `usuarios`
**Rol:** usuarios del modulo Consulta Padron. Niveles de acceso diferenciados. El superadmin se crea directamente en la base. Los demas se crean desde el ABM de usuarios.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | Clave primaria. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Nombre de usuario. Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('consulta','admin','superadmin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

**Niveles de acceso:**

| Nivel | Puede hacer |
|---|---|
| `consulta` | Buscador, listados, filtros. Solo lectura. |
| `admin` | Todo lo anterior mas ABM de referentes, partidos, trabajos y personas. |
| `superadmin` | Todo lo anterior mas ABM de usuarios. Hay uno solo. |

---

## 4. Tablas adicionales

Las tablas adicionales se cruzan contra `personas` por DNI via LEFT JOIN desde las vistas o desde queries predefinidas. No tienen FK declaradas contra `personas` porque pueden contener DNIs que no matchean.

**Disponibles actualmente:**

| Tabla | Registros | Descripcion |
|---|---|---|
| `st_siet_2026` | 8.411 | Empleados UBA segun SIET 2026. Campos: dni, apellido, nombre, mail, facultad, titulo. |
| `st_ucr_caba_2026` | 105.186 | Afiliados UCR CABA 2026. Campos: seccion (=comuna), circuito, apellido, nombre, genero, dni, domicilio. |
| `st_ucr_pba_2024` | 657.808 | Afiliados UCR PBA 2024. Campos: dni, apellido, nombre, seccion. |
| `sede_laboral` | 0 | Sede laboral por DNI. Pendiente listado. |

**Uso desde el front:** los cruces con estas tablas se exponen como listados predefinidos descargables en Excel. Los combos para fabricar cruces dinamicos son una mejora futura no prioritaria.

---

## 5. Tablas staging

Las tablas staging (prefijo `st_`) contienen los datos consolidados offline que sirvieron de fuente para poblar las tablas productivas. Se mantienen en la base como referencia historica. No tienen claves foraneas declaradas.

| Tabla | Registros | Rol |
|---|---|---|
| `st_carreras` | 6 | Catalogo de carreras con id=99/SD |
| `st_referentes` | 269 | Catalogo de referentes con id_origen de la fuente |
| `st_partidos` | 53 | Catalogo de partidos con id_origen de la fuente |
| `st_trabajo` | 75 | Catalogo de trabajos con id_origen de la fuente |
| `st_padron_cd_datos` | 19.528 | Padron CD enriquecido |
| `st_padron_cp_datos` | 4.560 | Padron CP enriquecido |
| `st_auxiliares_cp` | 454 | Auxiliares CP para filtrado |
| `st_votos_cd_24` | 3.826 | Votos CD 2024 |
| `st_votos_cp_24` | 1.400 | Votos CP 2024 |

---

## 6. Vistas principales

Las vistas son la unica interfaz entre la base de datos y el PHP. El PHP hace SELECT contra las vistas y nunca consulta las tablas directamente. La exportacion a Excel se construye dinamicamente desde el resultado de la vista sin modificar el codigo.

### `vista_padron_cd`
Perfil completo de cada habilitado para votar en CD. Cruza por DNI: `padron_cd` → `referentes_graduado` → `referentes` (x3) → `persona_partido` → `partidos` → `persona_trabajo` → `trabajos` → `sede_laboral` → `participacion_electoral` (elecciones CD 2021 y 2024). Todos los joins son LEFT JOIN.

### `vista_padron_cp`
Idem para CP. Incluye el campo `auxiliar`. Cruza participacion para las 4 elecciones de CP: 2017, 2019, 2021, 2024.

### Agregar una eleccion nueva
Requiere dos operaciones sobre las vistas:
1. Agregar el LEFT JOIN a `participacion_electoral` con el nuevo `id_eleccion`.
2. Agregar la columna CASE WHEN correspondiente en el SELECT.

El PHP no se modifica.

### Agregar una tabla nueva
Requiere una operacion sobre las vistas:
1. Agregar el LEFT JOIN a la nueva tabla por DNI.
2. Agregar las columnas a mostrar en el SELECT.

El PHP no se modifica.

---

## 7. Relaciones entre tablas

```
personas ──────────────── padron_cd               (dni)
personas ──────────────── padron_cp               (dni)
personas ──────────────── referentes_graduado     (dni)
personas ──────────────── persona_partido         (dni)
personas ──────────────── persona_trabajo         (dni)
personas ──────────────── participacion_electoral (dni)
personas ──────────────── sede_laboral            (dni, LEFT JOIN)
personas ──────────────── st_siet_2026            (dni, LEFT JOIN)
personas ──────────────── st_ucr_caba_2026        (dni, LEFT JOIN)
personas ──────────────── st_ucr_pba_2024         (dni, LEFT JOIN)

referentes ────────────── referentes_graduado     (id -> referente_1/2/3)
partidos ──────────────── persona_partido         (id -> id_partido)
trabajos ──────────────── persona_trabajo         (id -> id_trabajo)
elecciones ────────────── participacion_electoral (id -> id_eleccion)
```

---

## 8. Estado de carga de datos

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

**Nota:** los datos migrados son validos para desarrollo. La validacion profunda de consistencia se realiza antes de pasar a produccion.

---

## 9. Lo que este esquema deja preparado para Fiscalizacion

- `elecciones` ya existe con el campo `activa` para identificar la eleccion en curso.
- `participacion_electoral` recibira los registros del dia de la eleccion.
- Los padrones ya estan separados por tipo (cd/cp).
- Las tablas de mesas, fiscales y login de fiscales se agregaran como tablas nuevas sin modificar las existentes.

---

## 10. Lo que este esquema NO incluye todavia

- Tablas de mesas electorales y fiscales.
- Registro en tiempo real de votos (modulo Fiscalizacion).
- Login independiente para el modulo de Fiscalizacion.

---

## Resumen

El esquema centraliza la identidad de cada individuo en `personas` (DNI, apellido, nombre), mantiene los padrones CD y CP puros tal como los entrega la facultad, separa los vinculos con partido y trabajo en tablas independientes actualizables via ABM, reemplaza el historial electoral embebido por una tabla de participacion donde solo figuran los que votaron, e incorpora un catalogo de carreras con id=99 explicito para auxiliares de otras facultades. Todo el dato consolidado se expone a traves de dos vistas principales que el PHP consulta directamente. Incorporar una tabla nueva o una eleccion nueva implica extender las vistas: el codigo PHP no se modifica. La base esta migrada y lista para el desarrollo de la etapa Consulta Padron.
