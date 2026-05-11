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

- Login de mesas electorales y de usuarios admin/superadmin.
- Registro de votos en tiempo real durante el dia de la eleccion.
- Busqueda rapida en el padron desde el telefono del fiscal.
- Estadisticas en tiempo real para el admin.
- Gestion de mesas y usuarios desde el superadmin.

---

## Procesos electorales

Fiscalizacion cubre los cuatro procesos electorales de la facultad:

| Proceso | Tipo | Padron |
|---|---|---|
| Consejo Directivo | cd | padron_cd |
| Ciencia Politica | cp | padron_cp |
| Relaciones del Trabajo | rt | padron_rt |
| Sociologia | cs | padron_cs |

Cada proceso tiene sus propias mesas. No hay mesas mixtas. El fiscal solo ve el padron de su mesa.

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

- Dashboard con estado de mesas en tiempo real (logueadas / caidas) y conteo de votos por eleccion.
- Reset de mesa desde el dashboard (libera en_uso=0 para que el fiscal pueda reloguearse).
- Tab de listados: padron completo de cada eleccion con columna de voto del dia, filtrable por voto/no voto.

### Superadmin

- ABM de mesas: crear, editar, habilitar/deshabilitar, liberar mesa en uso.
- ABM de usuarios: crear admins, activar/desactivar.
- Puede desmarcar votos individuales (eliminar registro de votos_dia por DNI).

---

## Tablas propias de fiscalizacion

Estas tablas se agregan a fiscaliz_padron sin modificar las existentes.

### `mesas`

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `nombre` | VARCHAR(60) | Ej: CD M1, CP M3, RT M1. |
| `tipo` | ENUM('cd','cp','rt','cs') | Tipo de padron que atiende. |
| `password` | VARCHAR(255) | Hash bcrypt. Lo cambia superadmin. |
| `habilitada` | TINYINT(1) | 1 = aparece en combo login. Default 0. |
| `en_uso` | TINYINT(1) | 1 = hay sesion activa en esta mesa. Default 0. |
| `activa` | TINYINT(1) | 1 = puede recibir votos. Default 0. |

Logica de en_uso:
- Al login exitoso de una mesa: en_uso = 1.
- Al logout del fiscal: en_uso = 0.
- Si el telefono se cuelga o hay cambio de fiscal: admin pone en_uso = 0 desde el dashboard.
- El combo del login muestra solo mesas con habilitada = 1 AND en_uso = 0.

### `usuarios_fiscal`

Solo para superadmin y admin. Los fiscales no tienen fila aqui.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `usuario` | VARCHAR(60) | Nombre de usuario. Unico. |
| `password` | VARCHAR(255) | Hash bcrypt. |
| `nivel` | ENUM('superadmin','admin') | Nivel de acceso. |
| `activo` | TINYINT(1) | 1 activo, 0 baja logica. Default 1. |

### `votos_dia`

Registro en tiempo real del dia de la eleccion.
Al cerrar la eleccion sus registros se migran a participacion_electoral.
UNIQUE KEY sobre (dni, id_eleccion) para evitar doble voto a nivel motor.

| Campo | Tipo | Descripcion |
|---|---|---|
| `id` | INT | PK. AUTO_INCREMENT. |
| `dni` | INT UNSIGNED | DNI del votante. |
| `id_mesa` | INT | FK a mesas. |
| `id_eleccion` | INT | FK a elecciones. |
| `tipo_voto` | ENUM('regular','observado') | Default regular. |
| `timestamp` | DATETIME | Fecha y hora. Default CURRENT_TIMESTAMP. |

---

## Login

Pantalla unica con dos secciones visualmente separadas.

Seccion superior — Fiscales:
- Combo con mesas habilitadas y no en uso (habilitada=1 AND en_uso=0).
- Campo password.
- Al autenticar: sesion con rol=fiscal, id_mesa, tipo_mesa, nombre_mesa, id_eleccion.

Seccion inferior — Admin y Superadmin:
- Campo usuario.
- Campo password.
- Al autenticar: sesion con rol=admin o superadmin.

---

## Busqueda AJAX en la pantalla del fiscal

El fiscal escribe en un campo unico. El sistema detecta el tipo de input:

```
Numerico  → filtra por DNI en el padron de su mesa
Texto     → filtra por apellido y nombre en el padron de su mesa
```

La busqueda consulta el padron correspondiente al tipo de mesa:

| tipo_mesa | Tabla consultada |
|---|---|
| cd | padron_cd |
| cp | padron_cp |
| rt | padron_rt |
| cs | padron_cs |

Los resultados se actualizan en tiempo real sin recargar la pagina.
Las personas que ya votaron aparecen bloqueadas con badge YA VOTO.

---

## Sesion del fiscal

```
$_SESSION['rol']         = 'fiscal'
$_SESSION['id_mesa']     = id de la mesa
$_SESSION['tipo_mesa']   = 'cd', 'cp', 'rt' o 'cs'
$_SESSION['nombre_mesa'] = nombre legible de la mesa
$_SESSION['id_eleccion'] = id de la eleccion activa del tipo de mesa
```

El logout destruye la sesion y pone mesas.en_uso = 0.
El logout pide confirmacion para evitar toques accidentales en el telefono.

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
│   ├── auth.php            <- autenticacion independiente de consulta_padron
│   ├── navbar.php
│   ├── footer.php
│   └── funciones.php
├── modulos/
│   ├── login/
│   │   └── login.php
│   ├── logout/
│   │   └── logout.php
│   ├── error/
│   │   └── error.php
│   ├── fiscal/
│   │   └── fiscal.php      <- pantalla del fiscal: busqueda AJAX + voto
│   ├── dashboard/
│   │   └── dashboard.php   <- estado mesas + conteo votos (admin)
│   ├── listados/
│   │   └── listados.php    <- padron con voto del dia (admin)
│   ├── abm_mesas/
│   │   └── abm_mesas.php   <- superadmin
│   └── abm_usuarios/
│       └── abm_usuarios.php <- superadmin
└── assets/
    ├── css/
    │   └── estilos.css
    └── js/
        ├── main.js
        └── fiscal.js       <- AJAX de busqueda en tiempo real
```

---

## Routing

Mismo patron que consulta_padron. index.php decide el modulo segun ?mod=.
Cualquier excepcion no manejada redirige a error sin romper la sesion.

```
/?mod=fiscal        <- fiscal autenticado
/?mod=dashboard     <- admin, superadmin
/?mod=listados      <- admin, superadmin
/?mod=abm_mesas     <- superadmin
/?mod=abm_usuarios  <- superadmin
/?mod=error         <- todos
```

Sin mod carga login. Sin sesion redirige al login.

---

## Niveles de acceso

| Nivel | Modulos habilitados |
|---|---|
| fiscal | fiscal |
| admin | dashboard, listados |
| superadmin | todo lo anterior mas abm_mesas, abm_usuarios |

auth.php expone:
- verificar_sesion_fiscal() — cualquier rol autenticado.
- verificar_admin_fiscal() — admin o superadmin.
- verificar_superadmin_fiscal() — solo superadmin.

---

## Orden de desarrollo

```
1. DDL — mesas, usuarios_fiscal, votos_dia
2. Login — pantalla unica dos secciones
3. Pantalla fiscal — busqueda AJAX, confirmacion voto, logout
4. Dashboard — estado mesas + conteo votos (admin)
5. Listados — padron con voto del dia (admin)
6. ABM mesas — superadmin
7. ABM usuarios — superadmin
8. Migracion votos_dia a participacion_electoral al cierre
```

---

## Nota de collation

Igual que en consulta_padron, db.php debe incluir esta linea
despues de crear la conexion PDO:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Sin esta linea las queries que combinan vistas de distintas collations fallan.

---

## Documentacion relacionada

- [docs/propuesta_bbdd.md](../docs/propuesta_bbdd.md)
- [docs/convenciones.md](../docs/convenciones.md)
- [sql/estructura/fiscaliz_padron.sql](../sql/estructura/fiscaliz_padron.sql)
- [sql/migracion/migracion.md](../sql/migracion/migracion.md)
