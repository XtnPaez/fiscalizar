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
| Usuarios | Referentes politicos, admin | Fiscales, mira, admin, superadmin |
| Login | Propio, tabla usuarios | Propio, tablas mesas y usuarios_fiscal |
| Base de datos | fiscaliz_padron | fiscaliz_padron (compartida) |

Comparten la base pero no comparten codigo, usuarios ni sesiones.

---

## Que hace este modulo

- Administracion de elecciones, dias y mesas desde la interfaz (superadmin).
- Login de mesas electorales y de usuarios admin/superadmin/mira.
- Registro de votos en tiempo real durante el dia de la eleccion.
- Busqueda rapida en el padron desde el telefono del fiscal.
- Listados con voto del dia, filtros y buscador de persona (admin/superadmin).
- Consulta simplificada del padron propio con filtro de voto (mira).
- Registro y visualizacion de votos observados.
- Punteo de nuestra lista por corte y por mesa con proyecciones.
- Gestion de usuarios fiscales desde el superadmin.

---

## Jerarquia de datos electorales

```
ELECCION (cd / cp / rt / cs) — estado: programada / activa / cerrada
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
- Resultados: persona que no voto = seleccionable / ya voto = bloqueada con badge YA VOTO.
- Al seleccionar: nombre, DNI, radio button tipo de voto (regular / observado).
- Boton confirmar voto con modal de confirmacion.
- Boton logout con confirmacion.

Si el admin libera la mesa mientras el fiscal tiene sesion activa, el proximo
intento de registrar un voto muestra un aviso y redirige al logout automaticamente.

### Mira

Usuario de solo lectura para un padron especifico (cd/cp/rt/cs).
Se loguea con usuario y password desde la seccion de administradores.
Solo ve el modulo Consulta — su padron con filtro de voto y buscador.

### Admin

- Dashboard con conteo de votos de las elecciones activas.
- Listados: padron por eleccion con voto del dia, filtros (referente/partido/trabajo/voto) y buscador de persona.
- Observados: listado de votos observados de todas las elecciones activas.
- Punteo: carga de cortes por mesa y vista consolidada con proyecciones.

### Superadmin

- Todo lo que puede hacer el admin.
- Dashboard: ademas ve el estado de cada mesa (en uso / libre) y puede liberar mesas caidas.
- Administracion: ABM de elecciones, dias y mesas. ABM de usuarios fiscales.

---

## Niveles de acceso

| Nivel | Modulos habilitados |
|---|---|
| fiscal | fiscal |
| mira | consulta (solo su padron) |
| admin | dashboard, listados, observados, punteo |
| superadmin | todo lo anterior mas abm_elecciones, abm_usuarios |

---

## Tablas propias de fiscalizacion

### `elecciones` (modificada)

Campo `activa TINYINT(1)` reemplazado por `estado ENUM('programada','activa','cerrada')`.

| Estado | Descripcion |
|---|---|
| programada | Creada, sin activar. Dias y mesas pueden configurarse. |
| activa | En curso. Listados y consulta la muestran. Fiscales pueden loguearse. |
| cerrada | Finalizada. Votos migrados a participacion_electoral. |

### `dias_eleccion`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_eleccion` | INT | FK a elecciones. |
| `nombre` | VARCHAR(30) | Ej: Lunes, Martes. |
| `habilitado` | TINYINT(1) | 1 = mesas del dia visibles en login. Default 0. |

### `mesas`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(60) | Ej: CD-LU-M1. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de padron que atiende. |
| `id_dia` | INT | FK a dias_eleccion. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `en_uso` | TINYINT(1) | 1 = sesion activa. Default 0. |
| `activa` | TINYINT(1) | 1 = puede recibir votos. Default 1. |

Nota: el campo `habilitada` existe pero esta deprecado.
La habilitacion se hereda de `dias_eleccion.habilitado`.

### `votos_dia`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | DNI del votante. |
| `id_mesa` | INT | FK a mesas. |
| `tipo_voto` | ENUM('regular','observado') | Default regular. |
| `timestamp` | DATETIME | Default CURRENT_TIMESTAMP. |

UNIQUE KEY (dni, id_mesa). La eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones.
Al cerrar la eleccion, los registros se migran a participacion_electoral.

### `usuarios_fiscal`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('superadmin','admin','mira') | Nivel de acceso. |
| `tipo` | ENUM('cd','cp','rt','cs') NULL | Solo para nivel mira. Define el padron. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. |

### `punteo`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `id_mesa` | INT | FK a mesas. |
| `numero_corte` | INT | Asignado automaticamente (MAX+1 por mesa). |
| `votantes` | INT | Votantes en este corte. Normalmente 20, puede variar. |
| `faltantes` | INT | Boletas de nuestra lista que faltaron = votos estimados. |
| `timestamp` | DATETIME | Default CURRENT_TIMESTAMP. |

UNIQUE KEY (id_mesa, numero_corte). Acumulados calculados siempre, nunca almacenados.

---

## Vistas propias de fiscalizacion

| Vista | Fuente | Campo especial |
|---|---|---|
| vista_fiscal_cd | padron_cd | CARRERA. Sin AUXILIAR. |
| vista_fiscal_cp | padron_cp | AUXILIAR desde padron_cp.auxiliar. |
| vista_fiscal_rt | padron_rt | AUXILIAR desde auxiliares id_carrera=3. |
| vista_fiscal_cs | padron_cs | AUXILIAR desde auxiliares id_carrera=1. |

Todas incluyen VOTO_2026 desde votos_dia usando EXISTS + subquery sobre mesas
de la eleccion activa del tipo correspondiente (evita duplicados por multiples dias).

No reemplazan ni modifican las vistas de Consulta Padron.

---

## Login

Pantalla unica con dos secciones visualmente separadas.

Seccion superior — Fiscales:
- Combo con mesas de dias habilitados, elecciones activas, en_uso = 0.
- Campo password.
- Al autenticar: sesion con rol=fiscal, id_mesa, tipo_mesa, nombre_mesa, id_eleccion.

Seccion inferior — Admin, Superadmin y Mira:
- Campo usuario y password.
- Al autenticar segun nivel:
  - superadmin / admin → dashboard
  - mira → consulta (con tipo_mira en sesion)

Cookie de sesion con lifetime 24hs, secure, httponly, samesite Strict.
Persiste aunque el fiscal cierre el browser.

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
│   ├── consulta/
│   │   └── consulta.php
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

```
/?mod=login
/?mod=logout
/?mod=error
/?mod=fiscal
/?mod=dashboard
/?mod=listados
/?mod=observados
/?mod=punteo
/?mod=consulta
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
| Consulta | modulos/consulta/consulta.php | mira |
| ABM Elecciones | modulos/abm_elecciones/abm_elecciones.php | superadmin |
| ABM Usuarios | modulos/abm_usuarios/abm_usuarios.php | superadmin |

### Fiscal

Pantalla unica de busqueda y registro de voto. Busqueda AJAX por DNI o apellido.
Personas que ya votaron aparecen bloqueadas. Confirmacion con tipo (regular/observado).
Si la mesa es liberada por el admin durante la sesion, el proximo intento de voto
muestra aviso y redirige al logout automaticamente.

### Dashboard

Admin: conteo de votos por eleccion activa.
Superadmin: ademas ve estado de cada mesa con opcion de liberar mesas caidas.
Boton Actualizar para refrescar sin F5 (util en mobile).

### Listados

Dos secciones:

Buscador de persona: busca en todos los padrones de elecciones activas,
muestra en que padrones figura y si voto. Una fila por padron. Excel.

Listado por eleccion: combo de elecciones activas. Filtros: referente, partido,
trabajo, voto (SI/NO/Todos). Columnas segun tipo (CD con carrera, CP/RT/CS con auxiliar).
Excel con filtros activos.

### Observados

Votos observados de todas las elecciones activas. ELECCION | MESA | DNI | APELLIDO | NOMBRE.
Excel. Sin filtros.

### Punteo

Carga de cortes por mesa (votantes + faltantes, numero automatico, edicion via modal).
Consolidado por eleccion: tabla por mesa con %, fila TOTAL.
Proyecciones x 0.90, x 0.85 y factor variable con JS en tiempo real.
Cada proyeccion muestra resultado estimado y % estimado sobre votantes totales.

### Consulta

Solo para nivel mira. Muestra el padron de su tipo (cd/cp/rt/cs) en la eleccion activa.
Buscador por DNI, apellido o nombre. Filtro voto SI/NO/Todos.
Sin filtrar muestra mensaje de bienvenida. Al enviar el form con Todos muestra
el padron completo con columna voto SI/NO. Excel.

### ABM Elecciones

Tres pestanas:

Elecciones: crear (nombre/tipo/anio), activar, desactivar, cerrar y migrar votos.
Los botones Desactivar y Cerrar se deshabilitan si hay dias habilitados.

Dias: crear dias para una eleccion, habilitar/deshabilitar. Al deshabilitar un dia
libera automaticamente el en_uso de todas sus mesas.

Mesas: crear mesas (hereda tipo de la eleccion, bcrypt del password), editar nombre,
cambiar password, liberar mesa caida. Si se llega sin id_dia desde el navbar,
muestra selectores encadenados eleccion -> dia antes de las mesas.
Boton Actualizar para refrescar sin F5.

### ABM Usuarios

Listado con usuario/nivel/tipo/estado. Crear nuevo usuario con usuario, password, nivel
y — si el nivel es mira — tipo de padron (CD/CP/RT/CS). Editar nivel via modal.
Activar/desactivar. Cambiar password via modal.
El superadmin no puede desactivarse a si mismo.

---

## Autenticacion

auth.php expone cuatro funciones:

- verificar_sesion_fiscal() — cualquier rol autenticado.
- verificar_admin_fiscal() — admin o superadmin.
- verificar_superadmin_fiscal() — solo superadmin.
- verificar_mira_fiscal() — solo mira.

Los endpoints AJAX (buscar.php, registrar_voto.php) verifican sesion y rol
manualmente sin usar las funciones de auth.php.

---

## Exportacion a Excel

Archivo includes/excel.php, funcion exportar_excel($resultado, $nombre_archivo).
Las columnas se construyen dinamicamente desde las claves del primer registro.
Disponible en: listados, observados, punteo (pendiente), consulta.

---

## Scripts de mantenimiento

Ejecutar siempre en phpMyAdmin. Nunca exponer en el front.

**reset_dia.sql** — TRUNCATE votos_dia y punteo, UPDATE mesas SET en_uso = 0.
Usar antes de cada sesion de prueba.

**reset_total.sql** — borra mesas, dias, usuarios_fiscal, punteo y votos.
Resetea elecciones a programada. No toca Consulta Padron.
Despues de ejecutar hay que recrear el superadmin.

---

## Scripts de estructura

**fiscaliz_estructura_v2.sql** — cambios estructurales junio 2026:
reemplaza activa por estado ENUM, crea dias_eleccion, modifica mesas,
limpia votos y mesas de prueba.

**fiscaliz_mira.sql** — agrega nivel mira al ENUM de usuarios_fiscal
y campo tipo ENUM('cd','cp','rt','cs') NULL.

**vista_fiscal.sql** — crea las cuatro vistas de fiscalizacion.

---

## Nota de collation

db.php debe incluir despues de crear la conexion PDO:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Sin esta linea las queries que combinan vistas de distintas collations fallan.

---

## Pendientes

- Prueba completa del flujo electoral con datos reales.
- Migracion votos_dia -> participacion_electoral al cierre (implementada en
  ABM Elecciones — pendiente prueba con datos reales).
- Vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
