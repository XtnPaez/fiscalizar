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

| | Consulta Padron | Fiscalizacion |
|---|---|---|
| Subdominio | padron.fiscalizar.com.ar | fiscalizar.com.ar |
| Carpeta en el repo | consulta_padron/ | fiscalizacion/ |
| Rama de desarrollo | consulta-padron | fiscalizacion |
| Usuarios | Referentes politicos, admin | Fiscales, mira, admin, superadmin |
| Login | Propio, tabla usuarios | Propio, tablas mesas y usuarios_fiscal |
| Base de datos | fiscaliz_padron | fiscaliz_padron (compartida) |

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

## Padrones soportados

| Tipo | Tabla | Vista fiscal |
|---|---|---|
| CD | padron_cd | vista_fiscal_cd |
| CP | padron_cp | vista_fiscal_cp |
| RT | padron_rt | vista_fiscal_rt |
| CS | padron_cs | vista_fiscal_cs |
| CC | padron_cc | vista_fiscal_cc |

---

## Jerarquia de datos electorales

```
ELECCION (cd / cp / rt / cs / cc) — estado: programada / activa / cerrada
    └── DIA (Lunes, Martes, Miercoles...)
            └── MESA (CD-LU-M1, CP-MA-M2...)
                    └── VOTOS (votos_dia)
                    └── PUNTEO (punteo — cortes de nuestra lista)
```

---

## Roles y pantallas

### Fiscal

El fiscal se loguea con el nombre de su mesa y un password compartido.
Solo ve su pantalla de busqueda y registro de voto.

- Busqueda AJAX por DNI o apellido en el padron de su mesa.
- Personas que ya votaron aparecen bloqueadas con badge YA VOTO.
- Confirmacion de voto con tipo (regular / observado).
- Logout con confirmacion para evitar toques accidentales en mobile.

Si el admin libera la mesa mientras el fiscal tiene sesion activa, el proximo
intento de registrar un voto muestra aviso y redirige al logout automaticamente.

Si el fiscal cierra el browser sin logout, la mesa queda en_uso = 1 y no aparece
en el combo de login. El admin debe liberarla desde el dashboard.

### Mira

Usuario de solo lectura para un padron especifico (cd/cp/rt/cs/cc).
Se loguea desde la seccion de administradores con usuario y password.
Solo ve el modulo Consulta — su padron con filtro de voto y buscador.

### Admin

- Dashboard con conteo de votos de las elecciones activas.
- Listados: padron por eleccion con filtros (referente/partido/trabajo/voto) y buscador.
- Observados: votos observados de todas las elecciones activas.
- Punteo: carga de cortes y vista consolidada con proyecciones.

### Superadmin

- Todo lo que puede hacer el admin.
- Dashboard: ademas ve estado de mesas y puede liberar mesas caidas.
- Administracion: ABM de elecciones/dias/mesas y ABM de usuarios fiscales.

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
Tipo extendido a `ENUM('cd','cp','rt','cs','cc')`.

### `dias_eleccion`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_eleccion | INT FK elecciones |
| nombre | VARCHAR(30) |
| habilitado | TINYINT(1) DEFAULT 0 |

### `mesas`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| nombre | VARCHAR(60) |
| tipo | ENUM('cd','cp','rt','cs','cc') |
| id_dia | INT FK dias_eleccion |
| password | VARCHAR(255) bcrypt |
| en_uso | TINYINT(1) DEFAULT 0 |
| activa | TINYINT(1) DEFAULT 1 |

### `votos_dia`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| dni | INT UNSIGNED |
| id_mesa | INT FK mesas |
| tipo_voto | ENUM('regular','observado') DEFAULT 'regular' |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (dni, id_mesa).

### `usuarios_fiscal`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| usuario | VARCHAR(60) UNIQUE |
| password | VARCHAR(255) bcrypt |
| nivel | ENUM('superadmin','admin','mira') |
| tipo | ENUM('cd','cp','rt','cs','cc') NULL |
| activo | TINYINT(1) DEFAULT 1 |

### `punteo`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| id_mesa | INT FK mesas |
| numero_corte | INT — MAX+1 por mesa, automatico |
| votantes | INT DEFAULT 20 |
| faltantes | INT DEFAULT 0 |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (id_mesa, numero_corte).

### `padron_cc`

| Campo | Tipo |
|---|---|
| id | BIGINT UNSIGNED PK AUTO_INCREMENT |
| dni | INT UNSIGNED FK personas |
| apellido | VARCHAR(120) |
| nombre | VARCHAR(120) |

4.504 registros. 153 auxiliares identificados (no estaban en padron_cd).

---

## Vistas de fiscalizacion

| Vista | Fuente | Auxiliar |
|---|---|---|
| vista_fiscal_cd | padron_cd | No aplica |
| vista_fiscal_cp | padron_cp | padron_cp.auxiliar |
| vista_fiscal_rt | padron_rt | auxiliares id_carrera=3 |
| vista_fiscal_cs | padron_cs | auxiliares id_carrera=1 |
| vista_fiscal_cc | padron_cc | auxiliares id_carrera=5 |

Todas incluyen VOTO_2026 desde votos_dia via EXISTS + subquery.
No reemplazan ni modifican las vistas de Consulta Padron.

Nota: vista_fiscal_cc tiene collation utf8mb4_general_ci por mezcla con padron_cc.
Al filtrar por voto_2026 usar COLLATE utf8mb4_unicode_ci explicito en el WHERE.

---

## Login

Cookie de sesion con lifetime 24hs, secure, httponly, samesite Strict.

Seccion fiscal: combo de mesas habilitadas con en_uso = 0.
Seccion admin/superadmin/mira: usuario y password.
- superadmin/admin → dashboard
- mira → consulta (tipo_mira en sesion)

---

## Estructura de carpetas

```
fiscalizacion/
├── README.md
├── composer.json
├── index.php
├── config/
│   ├── db.php
│   └── db.example.php
├── includes/
│   ├── auth.php
│   ├── navbar.php
│   ├── footer.php
│   ├── funciones.php
│   └── excel.php
├── modulos/
│   ├── login/login.php
│   ├── logout/logout.php
│   ├── error/error.php
│   ├── fiscal/
│   │   ├── fiscal.php
│   │   ├── buscar.php
│   │   └── registrar_voto.php
│   ├── dashboard/dashboard.php
│   ├── listados/listados.php
│   ├── observados/observados.php
│   ├── punteo/punteo.php
│   ├── consulta/consulta.php
│   ├── abm_elecciones/abm_elecciones.php
│   └── abm_usuarios/abm_usuarios.php
└── assets/
    ├── css/estilos.css
    └── js/
        ├── main.js
        └── fiscal.js
```

---

## Routing

```
/?mod=login / logout / error / fiscal / dashboard
/?mod=listados / observados / punteo / consulta
/?mod=abm_elecciones / abm_usuarios
```

---

## Modulos

### Fiscal
Busqueda AJAX y registro de voto. Detecta liberacion de mesa en tiempo real.

### Dashboard
Admin: conteo de votos. Superadmin: ademas estado de mesas y liberacion.
Boton Actualizar para mobile.

### Listados
Buscador de persona en todos los padrones activos. Listado por eleccion con
filtros referente/partido/trabajo/voto (SI/NO/Todos). Excel con filtros activos.

### Observados
Votos observados de todas las elecciones activas. ELECCION|MESA|DNI|APELLIDO|NOMBRE. Excel.

### Punteo
Carga de cortes (votantes + faltantes, numero automatico, edicion via modal).
Consolidado por eleccion con %. Proyecciones x0.90, x0.85 y factor variable JS.

### Consulta
Solo nivel mira. Padron de su tipo en la eleccion activa.
Buscador por DNI/apellido/nombre. Filtro voto SI/NO/Todos. Excel.
Nota: usa COLLATE utf8mb4_unicode_ci en el filtro voto para evitar error 1267.

### ABM Elecciones
Pestana Elecciones: crear (cd/cp/rt/cs/cc), activar, desactivar, cerrar y migrar votos.
Pestana Dias: crear, habilitar/deshabilitar (libera en_uso al deshabilitar).
Pestana Mesas: crear, editar nombre, cambiar password, liberar. Selectores
encadenados si se llega sin id_dia desde el navbar. Boton Actualizar.

### ABM Usuarios
Crear usuario con nivel (admin/superadmin/mira) y — si es mira — tipo de padron
(cd/cp/rt/cs/cc). Editar nivel, cambiar password, activar/desactivar via modales.
El superadmin no puede desactivarse a si mismo.

---

## Autenticacion

- verificar_sesion_fiscal() — cualquier rol
- verificar_admin_fiscal() — admin o superadmin
- verificar_superadmin_fiscal() — solo superadmin
- verificar_mira_fiscal() — solo mira

Acceso denegado redirige a logout (no a login) para evitar loops de redirect.

---

## Scripts de mantenimiento

**reset_dia.sql** — TRUNCATE votos_dia y punteo, UPDATE mesas SET en_uso = 0.

**reset_total.sql** — borra mesas, dias, usuarios_fiscal, punteo y votos.
Resetea elecciones a programada. No toca Consulta Padron ni padron_cc.

---

## Scripts de estructura

**fiscaliz_estructura_v2.sql** — esquema base junio 2026.
**fiscaliz_mira.sql** — nivel mira y campo tipo en usuarios_fiscal.
**fiscaliz_padron_cc.sql** — padron_cc, auxiliares CC, ENUMs extendidos, vista_fiscal_cc.
**vista_fiscal.sql** — cuatro vistas cd/cp/rt/cs.

---

## Nota de collation

db.php debe incluir:
```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

La vista_fiscal_cc puede generar error 1267 al filtrar por voto_2026.
Se resuelve con COLLATE utf8mb4_unicode_ci explicito en el WHERE del PHP.

---

## Pendientes

- Prueba completa del flujo electoral con datos reales (martes).
- Validacion de migracion votos_dia -> participacion_electoral al cierre.
- Vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
- Recrear vista_fiscal_cc con COLLATE explicito para eliminar el workaround del PHP.
