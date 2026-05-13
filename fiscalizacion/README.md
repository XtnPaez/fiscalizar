# Fiscalizacion

![Estado](https://img.shields.io/badge/estado-en_desarrollo-blue)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![MariaDB](https://img.shields.io/badge/MariaDB-10.6-4479A1)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3)

Segunda etapa del sistema Fiscalizar. Modulo electoral completo para el registro
y seguimiento de votos en tiempo real durante el dia de la eleccion.

Accesible desde: fiscalizar.com.ar

---

## Relacion con Consulta Padron

Fiscalizacion y Consulta Padron son dos desarrollos independientes que comparten
la misma base de datos (fiscaliz_padron).

| | Consulta Padron | Fiscalizacion |
|---|---|---|
| Subdominio | padron.fiscalizar.com.ar | fiscalizar.com.ar |
| Carpeta en el repo | consulta_padron/ | fiscalizacion/ |
| Rama de desarrollo | consulta-padron | fiscalizacion |
| Usuarios | Referentes politicos, admin | Fiscales, admin electoral, superadmin |
| Login | Propio, tabla usuarios | Propio, tablas mesas y usuarios_fiscal |
| Base de datos | fiscaliz_padron | fiscaliz_padron (compartida) |

Comparten la base pero no comparten codigo, usuarios ni sesiones.
Un usuario de Consulta Padron no tiene acceso a Fiscalizacion y viceversa.

---

## Que hace este modulo

- Administracion de elecciones, dias y mesas desde la interfaz (superadmin).
- Login de mesas electorales y de usuarios admin/superadmin.
- Registro de votos en tiempo real durante el dia de la eleccion.
- Busqueda rapida en el padron desde el telefono del fiscal.
- Listados con voto del dia y buscador de persona por padron.
- Registro y visualizacion de votos observados.
- Punteo de nuestra lista por corte y por mesa con proyecciones.
- Gestion de usuarios fiscales desde el superadmin.

---

## Jerarquia de datos electorales

```
ELECCION (cd / cp / rt / cs)
    └── DIA (Lunes, Martes, Miercoles...)
            └── MESA (CD-LU-M1, CP-MA-M2...)
                    └── VOTOS (votos_dia)
                    └── PUNTEO (punteo — cortes de nuestra lista)
```

La habilitacion opera a nivel DIA. Habilitar un dia hace que todas sus mesas
aparezcan en el combo de login del fiscal. El cierre de una eleccion requiere
que todos sus dias esten deshabilitados.

---

## Roles y pantallas

### Fiscal

El fiscal se loguea con el nombre de su mesa y un password compartido.
No tiene usuario propio. La mesa es el usuario.

Pantalla unica:
- Nombre de la mesa (solo lectura).
- Campo de busqueda por DNI o apellido (filtra en tiempo real via AJAX).
- Resultados: persona que no voto = seleccionable / persona que ya voto = bloqueada con badge YA VOTO.
- Al seleccionar: nombre, DNI, radio button tipo de voto (regular por defecto / observado).
- Boton confirmar voto.
- Boton logout con confirmacion (evita logout accidental en telefono).

El fiscal no ve estadisticas de ningun tipo.

### Admin

- Dashboard con conteo de votos de las elecciones activas. No ve estado de mesas.
- Listados: padron completo por eleccion con voto del dia, filtros y buscador de persona.
- Observados: listado de votos observados de todas las elecciones activas.
- Punteo: carga de cortes por mesa y vista consolidada con proyecciones.

### Superadmin

- Todo lo que puede hacer el admin.
- Dashboard: ademas ve el estado de cada mesa (en uso / libre) y puede liberar mesas caidas.
- Administracion: ABM de elecciones, dias y mesas. ABM de usuarios fiscales.

---

## Tablas propias de fiscalizacion

Estas tablas se agregan a fiscaliz_padron sin modificar las tablas de Consulta Padron.

### `elecciones` (modificada)

El campo `activa TINYINT(1)` fue reemplazado por `estado ENUM('programada','activa','cerrada')`.

| Estado | Descripcion |
|---|---|
| programada | Creada, sin activar. Dias y mesas pueden configurarse. |
| activa | En curso. Los listados la muestran. Los fiscales pueden loguearse. |
| cerrada | Finalizada. Votos migrados a participacion_electoral. |

Solo puede haber una eleccion activa por tipo (cd/cp/rt/cs) simultaneamente.

### `dias_eleccion`

Nivel intermedio entre elecciones y mesas. La habilitacion opera a este nivel.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_eleccion` | INT | FK a elecciones. |
| `nombre` | VARCHAR(30) | Ej: Lunes, Martes, Miercoles. |
| `habilitado` | TINYINT(1) | 1 = mesas del dia visibles en el login. Default 0. |

### `mesas`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(60) | Ej: CD-LU-M1, CP-MA-M2. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de padron que atiende. |
| `id_dia` | INT | FK a dias_eleccion. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `en_uso` | TINYINT(1) | 1 = hay sesion activa. Default 0. |
| `activa` | TINYINT(1) | 1 = puede recibir votos. Default 1. |

Nota: el campo `habilitada` existe en la tabla pero esta deprecado. La habilitacion
se hereda de `dias_eleccion.habilitado`.

### `votos_dia`

Registro en tiempo real del dia de la eleccion.
Al cerrar la eleccion sus registros se migran a participacion_electoral.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | DNI del votante. |
| `id_mesa` | INT | FK a mesas. |
| `tipo_voto` | ENUM('regular','observado') | Default regular. |
| `timestamp` | DATETIME | Fecha y hora. Default CURRENT_TIMESTAMP. |

UNIQUE KEY sobre (dni, id_mesa): un DNI no puede votar dos veces en la misma mesa.
La eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones.

### `usuarios_fiscal`

Solo para superadmin y admin. Los fiscales no tienen fila aqui.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Nombre de usuario. Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('superadmin','admin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. Default 1. |

### `punteo`

Registro del punteo de nuestra lista por corte y por mesa.
Un corte = cada vez que los fiscales entran al cuarto oscuro a reponer boletas.
Normalmente cada 20 votantes, pero el numero real se ingresa en cada corte.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_mesa` | INT | FK a mesas. |
| `numero_corte` | INT | Numero de corte dentro de la mesa. Asignado automaticamente. |
| `votantes` | INT | Votantes en este corte. Normalmente 20, puede variar. |
| `faltantes` | INT | Boletas de nuestra lista que faltaron = votos estimados. |
| `timestamp` | DATETIME | Fecha y hora. Default CURRENT_TIMESTAMP. |

UNIQUE KEY sobre (id_mesa, numero_corte).
Los acumulados (total votantes, total faltantes, porcentaje) se calculan siempre,
nunca se almacenan.

---

## Vistas propias de fiscalizacion

Cuatro vistas creadas especificamente para este modulo. No reemplazan ni modifican
las vistas de Consulta Padron (vista_padron_cd, vista_padron_cp).

| Vista | Fuente | Diferencia con Consulta Padron |
|---|---|---|
| vista_fiscal_cd | padron_cd | VOTO_2026 desde votos_dia. Incluye CARRERA. Sin AUXILIAR. |
| vista_fiscal_cp | padron_cp | VOTO_2026 desde votos_dia. Incluye AUXILIAR. Sin CARRERA. |
| vista_fiscal_rt | padron_rt | VOTO_2026 desde votos_dia. AUXILIAR desde tabla auxiliares (id_carrera=3). |
| vista_fiscal_cs | padron_cs | VOTO_2026 desde votos_dia. AUXILIAR desde tabla auxiliares (id_carrera=1). |

El campo VOTO_2026 usa EXISTS + subquery sobre mesas de la eleccion activa del tipo
correspondiente para evitar duplicados cuando una eleccion tiene mas de un dia.

---

## Login

Pantalla unica con dos secciones visualmente separadas.

Seccion superior — Fiscales:
- Combo con mesas cuyos dias estan habilitados, de elecciones activas, no en uso.
- Campo password.
- Al autenticar: sesion con rol=fiscal, id_mesa, tipo_mesa, nombre_mesa, id_eleccion.

Seccion inferior — Admin y Superadmin:
- Campo usuario.
- Campo password.
- Al autenticar: sesion con rol=admin o superadmin.

---

## Estructura de carpetas

```
fiscalizacion/
├── README.md
├── composer.json
├── index.php
├── config/
│   ├── db.php              <- nunca se commitea
│   └── db.example.php
├── includes/
│   ├── auth.php
│   ├── navbar.php
│   ├── footer.php
│   ├── funciones.php
│   └── excel.php
├── modulos/
│   ├── login/
│   │   └── login.php
│   ├── logout/
│   │   └── logout.php
│   ├── error/
│   │   └── error.php
│   ├── fiscal/
│   │   ├── fiscal.php
│   │   ├── buscar.php          <- endpoint AJAX
│   │   └── registrar_voto.php  <- endpoint AJAX
│   ├── dashboard/
│   │   └── dashboard.php
│   ├── listados/
│   │   └── listados.php
│   ├── observados/
│   │   └── observados.php
│   ├── punteo/
│   │   └── punteo.php
│   ├── abm_elecciones/
│   │   └── abm_elecciones.php
│   └── abm_usuarios/
│       └── abm_usuarios.php
└── assets/
    ├── css/
    │   └── estilos.css
    └── js/
        ├── main.js
        └── fiscal.js
```

---

## Routing

Mismo patron que Consulta Padron. index.php decide el modulo segun ?mod=.

```
/?mod=login
/?mod=logout
/?mod=error
/?mod=fiscal
/?mod=dashboard
/?mod=listados
/?mod=observados
/?mod=punteo
/?mod=abm_elecciones
/?mod=abm_usuarios
```

Sin mod carga login. Sin sesion redirige al login.

---

## Modulos

| Modulo | Archivo | Acceso |
|---|---|---|
| Login | modulos/login/login.php | Publico |
| Logout | modulos/logout/logout.php | Todos |
| Error | modulos/error/error.php | Todos |
| Fiscal | modulos/fiscal/fiscal.php | fiscal |
| Dashboard | modulos/dashboard/dashboard.php | admin, superadmin |
| Listados | modulos/listados/listados.php | admin, superadmin |
| Observados | modulos/observados/observados.php | admin, superadmin |
| Punteo | modulos/punteo/punteo.php | admin, superadmin |
| ABM Elecciones | modulos/abm_elecciones/abm_elecciones.php | superadmin |
| ABM Usuarios | modulos/abm_usuarios/abm_usuarios.php | superadmin |

### Fiscal

Pantalla unica de busqueda y registro de voto. Busqueda AJAX por DNI o apellido
en el padron del tipo de mesa (cd/cp/rt/cs). Personas que ya votaron aparecen
bloqueadas. Confirmacion de voto con tipo (regular/observado). Logout con
confirmacion y pantalla de agradecimiento.

### Dashboard

Admin: conteo de votos por eleccion activa.
Superadmin: ademas ve estado de cada mesa (en uso / libre) con opcion de liberar
mesas caidas sin necesidad de ir al ABM.

### Listados

Dos secciones:

Buscador de persona: input unico (apellido o DNI), busca en todos los padrones
de elecciones activas, muestra en que padrones figura y si voto en cada uno.
Una fila por padron. Descargable en Excel.

Listado por eleccion: combo de elecciones activas, filtros opcionales por
referente/partido/trabajo, tabla con DNI/apellido/nombre/carrera-o-auxiliar/voto.
Descargable en Excel con los filtros activos.

### Observados

Listado de todos los votos observados de todas las elecciones activas juntas.
Columnas: ELECCION | MESA | DNI | APELLIDO | NOMBRE. Descargable en Excel.
Sin filtros. Se usa durante el escrutinio para resolver cada caso.

### Punteo

Registro del punteo de nuestra lista durante el dia de la eleccion.

Seccion 1 — Carga por mesa: elegir eleccion y mesa, ver cortes cargados
con % por corte y acumulado, cargar nuevo corte (votantes + faltantes),
editar cortes existentes via modal. El numero de corte es automatico.

Seccion 2 — Consolidado por eleccion: tabla por mesa con cortes/votantes/
faltantes/%, fila TOTAL. Proyecciones sobre el total de faltantes:
x 0.90, x 0.85, y factor variable calculado en tiempo real con JS.
Cada proyeccion muestra resultado estimado y % estimado sobre votantes totales.

### ABM Elecciones

Tres pestanas:

Pestana Elecciones: crear nueva eleccion (nombre/tipo/anio), activar (verifica
que no haya otra activa del mismo tipo), desactivar (verifica dias deshabilitados),
cerrar y migrar votos a participacion_electoral (marca eleccion como cerrada).

Pestana Dias: elegir eleccion, crear dias, habilitar/deshabilitar. Al deshabilitar
un dia libera automaticamente el en_uso de todas sus mesas.

Pestana Mesas: elegir dia, crear mesas (hereda tipo de la eleccion, genera hash
bcrypt del password), liberar mesa caida, cambiar password via modal.

### ABM Usuarios

Solo superadmin. Listado con nombre/nivel/estado. Crear nuevo usuario (usuario,
password, nivel). Editar nivel via modal. Activar/desactivar. Cambiar password
via modal. El superadmin no puede desactivarse a si mismo.

---

## Autenticacion

auth.php expone tres funciones:

- verificar_sesion_fiscal() — cualquier rol autenticado.
- verificar_admin_fiscal() — admin o superadmin.
- verificar_superadmin_fiscal() — solo superadmin.

Los endpoints AJAX (buscar.php, registrar_voto.php) verifican sesion y rol
manualmente sin usar las funciones de auth.php porque no tienen acceso al
contexto del router.

---

## Niveles de acceso

| Nivel | Modulos habilitados |
|---|---|
| fiscal | fiscal |
| admin | dashboard, listados, observados, punteo |
| superadmin | todo lo anterior mas abm_elecciones, abm_usuarios |

---

## Exportacion a Excel

Archivo includes/excel.php, funcion exportar_excel($resultado, $nombre_archivo).
Identica a la de Consulta Padron. Copiada para mantener independencia entre modulos.
Las columnas se construyen dinamicamente desde las claves del primer registro.

---

## Scripts de mantenimiento

Ejecutar siempre en phpMyAdmin directamente. Nunca exponer en el front.

**reset_dia.sql** — limpia votos_dia y punteo, libera en_uso de todas las mesas.
Usar antes de cada sesion de prueba.

**reset_total.sql** — borra mesas, dias, usuarios_fiscal, punteo y votos.
Resetea estado de elecciones a programada. No toca Consulta Padron.
Despues de ejecutar hay que recrear el superadmin.

---

## Nota de collation

db.php debe incluir esta linea despues de crear la conexion PDO:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Sin esta linea las queries que combinan vistas de distintas collations fallan
con error 1271.

---

## Pendientes

- Validacion profunda de consistencia de datos antes del pase a produccion real.
- Modulo de migracion votos_dia -> participacion_electoral al cierre (implementado
  en ABM Elecciones — pendiente prueba con datos reales).
- Vistas vista_padron_cd y vista_padron_cp de Consulta Padron: evaluar en version 2
  si incorporan votos de elecciones RT y CS cuando esten disponibles.
