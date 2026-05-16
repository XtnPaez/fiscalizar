# Fiscalizacion

![Estado](https://img.shields.io/badge/estado-produccion-green)
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
ELECCION (cd/cp/rt/cs/cc) — estado: programada/activa/cerrada
    └── DIA (Lunes, Martes...)
            └── MESA (CD-LU-M1...)  — activa: 1/0 (individual)
                    └── VOTOS (votos_dia)
                    └── PUNTEO (punteo)
```

La habilitacion opera a nivel DIA. Una mesa puede ademas deshabilitarse
individualmente (mesas.activa = 0) sin afectar el resto del dia.

---

## Roles y pantallas

### Fiscal

Logueo con nombre de mesa y password. Solo ve su pantalla de busqueda y voto.

- Busqueda AJAX por DNI o apellido en el padron de su tipo.
- Personas que ya votaron aparecen bloqueadas con badge YA VOTO.
- Confirmacion de voto con tipo (regular / observado).
- Logout con confirmacion para evitar toques accidentales en mobile.
- Si el admin libera o deshabilita la mesa, el proximo intento de voto
  muestra aviso y redirige al logout automaticamente.
- Si cierra el browser sin logout, la mesa queda en_uso=1 y no aparece
  en el combo hasta que el admin la libere.

### Mira

Usuario de solo lectura para un padron especifico (cd/cp/rt/cs/cc).
Logueo desde la seccion de administradores. Solo ve el modulo Consulta.

### Admin

- Dashboard: conteo acumulado de votos de todas las elecciones activas.
- Listados, Observados, Punteo.

### Superadmin

- Todo lo que puede hacer el admin.
- Dashboard: ademas ve estado de mesas del dia habilitado y puede liberar mesas.
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

Campo activa reemplazado por estado ENUM('programada','activa','cerrada').
Tipo extendido a ENUM('cd','cp','rt','cs','cc').

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

`activa` controla la habilitacion individual de la mesa.
`habilitada` (campo viejo) existe pero esta deprecado.

El dashboard muestra solo mesas con activa=1 del dia con habilitado=1.
El login del fiscal filtra por activa=1 AND en_uso=0 AND dia habilitado=1.

### `votos_dia`

| Campo | Tipo |
|---|---|
| id | INT PK AUTO_INCREMENT |
| dni | INT UNSIGNED |
| id_mesa | INT FK mesas |
| tipo_voto | ENUM('regular','observado') DEFAULT 'regular' |
| timestamp | DATETIME DEFAULT CURRENT_TIMESTAMP |

UNIQUE KEY (dni, id_mesa).
La eleccion se obtiene via id_mesa -> dias_eleccion -> elecciones.
Al cerrar la eleccion se migra a participacion_electoral.

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

4.504 registros. 153 auxiliares (id_carrera=5).

---

## Vistas de fiscalizacion

| Vista | Fuente | Auxiliar | Nota |
|---|---|---|---|
| vista_fiscal_cd | padron_cd | No aplica | Incluye CARRERA |
| vista_fiscal_cp | padron_cp | padron_cp.auxiliar | |
| vista_fiscal_rt | padron_rt | auxiliares id_carrera=3 | |
| vista_fiscal_cs | padron_cs | auxiliares id_carrera=1 | |
| vista_fiscal_cc | padron_cc | auxiliares id_carrera=5 | COLLATE workaround en PHP |

Todas incluyen VOTO_2026 via EXISTS sobre mesas activas (m.activa=1) de la
eleccion activa del tipo correspondiente.

Nota collation vista_fiscal_cc: usar COLLATE utf8mb4_unicode_ci en WHERE voto_2026.
Pendiente: recrear vista_fiscal_cc con COLLATE explicito.

---

## Login

Cookie de sesion 24hs, secure, httponly, samesite Strict.

Combo fiscal: mesas con d.habilitado=1 AND m.activa=1 AND m.en_uso=0 AND e.estado='activa'.
Autenticacion admin/mira: nivel determina modulo de destino.

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
Busqueda AJAX y registro de voto. Detecta liberacion/deshabilitacion de mesa.

### Dashboard
Admin: conteo acumulado de votos por eleccion activa (todos los dias).
Superadmin: ademas estado de mesas del dia habilitado con opcion de liberar.
Boton Actualizar para mobile.

### Listados
Buscador de persona en todos los padrones activos (mesas activas).
Listado por eleccion con filtros referente/partido/trabajo/voto. Excel.

### Observados
Votos observados de todas las elecciones activas. Excel. Sin filtros.

### Punteo
Carga de cortes por mesa. Consolidado por eleccion con proyecciones.

### Consulta
Solo nivel mira. Padron de su tipo. Filtro voto SI/NO/Todos. Excel.

### ABM Elecciones
Pestana Elecciones: crear (cd/cp/rt/cs/cc), activar, desactivar, cerrar y migrar.
Pestana Dias: crear, habilitar/deshabilitar dia entero.
Pestana Mesas: crear, editar nombre, habilitar/deshabilitar individual,
cambiar password, liberar mesa caida, eliminar (solo sin votos y sin uso).
Boton Actualizar. Selectores encadenados si se llega sin id_dia.

### ABM Usuarios
Crear con nivel (admin/superadmin/mira) y tipo para mira (cd/cp/rt/cs/cc).
Editar nivel, cambiar password, activar/desactivar.
El superadmin no puede desactivarse a si mismo.

---

## Autenticacion

- verificar_sesion_fiscal() — cualquier rol
- verificar_admin_fiscal() — admin o superadmin
- verificar_superadmin_fiscal() — solo superadmin
- verificar_mira_fiscal() — solo mira

Acceso denegado redirige a logout para evitar loops de redirect.

---

## Scripts de mantenimiento

**reset_dia.sql** — TRUNCATE votos_dia y punteo, libera en_uso de mesas.

**reset_total.sql** — borra mesas, dias, usuarios_fiscal, punteo y votos.
Resetea elecciones a programada. No toca padrones ni Consulta Padron.

---

## Scripts de estructura

| Script | Descripcion |
|---|---|
| fiscaliz_estructura_v2.sql | Esquema base junio 2026 |
| fiscaliz_mira.sql | Nivel mira y campo tipo en usuarios_fiscal |
| fiscaliz_padron_cc.sql | padron_cc, auxiliares CC, ENUMs, vista_fiscal_cc |
| fiscaliz_activa_mesa.sql | Cinco vistas con AND m.activa=1. Dashboard filtrado |
| vista_fiscal.sql | Cuatro vistas cd/cp/rt/cs (version inicial, reemplazada por fiscaliz_activa_mesa.sql) |

---

## Nota de collation

db.php debe incluir:
```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

vista_fiscal_cc: usar COLLATE utf8mb4_unicode_ci en WHERE voto_2026 desde PHP.

---

## Procedimiento de arranque el dia de la eleccion

```
1. Verificar que las elecciones esten en estado 'activa'
2. Verificar que los dias esten creados y deshabilitados
3. Verificar que las mesas esten creadas con activa=1
4. 1 minuto antes: habilitar el dia desde ABM Elecciones -> Dias
5. Fiscales se loguean
6. Durante el dia: dashboard para monitorear estado de mesas
7. Al cierre: deshabilitar el dia
8. Si hay mas dias: repetir desde paso 4
9. Al cerrar la eleccion: ABM Elecciones -> Elecciones -> Cerrar y migrar
```

---

## Pendientes

- Prueba completa del flujo electoral con datos reales.
- Validacion de migracion votos_dia -> participacion_electoral al cierre.
- Recrear vista_fiscal_cc con COLLATE explicito para eliminar workaround PHP.
- Vistas vista_padron_rt y vista_padron_cs para futura version de Consulta Padron.
- Caso CARASSAI (DNI 22735140) — revision manual de referentes pendiente.
