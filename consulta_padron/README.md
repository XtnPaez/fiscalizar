# Consulta Padron

![Estado](https://img.shields.io/badge/estado-en_desarrollo-blue)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3)
![PhpSpreadsheet](https://img.shields.io/badge/PhpSpreadsheet-2.0-217346)

Primera etapa del sistema Fiscalizar. Aplicacion web en PHP puro para consultar, filtrar y exportar los padrones electorales de la Facultad de Ciencias Sociales (UBA).

La aplicacion consume exclusivamente las vistas vista_padron_cd y vista_padron_cp de la base fiscaliz_padron. Nunca consulta tablas directamente.

---

## Entorno

| Etapa | Entorno |
|---|---|
| Desarrollo | Local — XAMPP en C:\xampp\htdocs\fiscalizar\consulta_padron |
| Aprobacion | padron.fiscalizar.com.ar |
| Produccion | padron.fiscalizar.com.ar |

Stack: PHP 8.1, MariaDB 10.6, Bootstrap 5, JavaScript nativo, PhpSpreadsheet via Composer. Sin frameworks PHP.

---

## Instalacion local

Ver [docs/instalacion.md](../docs/instalacion.md) para instrucciones completas paso a paso.

Resumen:

1. Clonar el repositorio en C:\xampp\htdocs\fiscalizar
2. Copiar config/db.example.php como config/db.php y completar con credenciales locales
3. Ejecutar composer install dentro de consulta_padron/
4. Importar fiscaliz_padron.sql en phpMyAdmin
5. Acceder desde http://localhost/fiscalizar/consulta_padron/

---

## Configuracion de base de datos

El archivo config/db.php incluye la siguiente linea despues de crear la conexion PDO:

```php
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
```

Esta linea es obligatoria. Sin ella, las queries que combinan vista_padron_cd
(utf8mb4_spanish_ci) con vista_padron_cp (utf8mb4_unicode_ci) via UNION fallan
con error 1271 de collation. Se resuelve forzando una collation uniforme
en toda la sesion desde la conexion.

---

## Diseño visual

| Elemento | Valor |
|---|---|
| Framework CSS | Bootstrap 5 via CDN |
| Fuente | Inter via Google Fonts |
| Navbar y footer | #1a1a2e (azul muy oscuro) |
| Acento principal | #4f8ef7 (azul medio) |
| Fondo de pagina | #f0f2f5 (gris claro) |
| Texto principal | #1a1a2e |
| Texto secundario | #4a5568 |

Principio de diseño: el sistema es una herramienta de trabajo. Todo lo que se ve en pantalla es un listado tabular, igual a lo que se va a descargar en Excel. Sin fichas, sin cajas decorativas, sin cards. La informacion al frente.

---

## Estructura de carpetas

```
consulta_padron/
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
│   ├── buscador/
│   │   └── buscador.php
│   ├── listados/
│   │   └── listados.php
│   ├── filtros/
│   │   └── filtros.php
│   ├── abm_referentes/
│   │   └── abm_referentes.php
│   ├── abm_partidos/
│   │   └── abm_partidos.php
│   ├── abm_trabajos/
│   │   └── abm_trabajos.php
│   ├── abm_personas/
│   │   └── abm_personas.php
│   └── abm_usuarios/
│       └── abm_usuarios.php
└── assets/
    ├── css/
    │   └── estilos.css
    └── js/
        └── main.js
```

---

## Routing

No hay framework de routing. index.php recibe todos los requests y decide que modulo cargar segun el parametro mod en la URL.

```
/?mod=buscador
/?mod=listados
/?mod=filtros
/?mod=abm_referentes
/?mod=abm_partidos
/?mod=abm_trabajos
/?mod=abm_personas
/?mod=abm_usuarios
```

Sin parametro mod carga el buscador por defecto. Sin sesion activa redirige al login.

---

## Autenticacion

Sistema de login propio, independiente del modulo de Fiscalizacion. Los usuarios viven en la tabla usuarios de fiscaliz_padron.

| Nivel | Puede hacer |
|---|---|
| consulta | Buscador, listados, filtros. Solo lectura. |
| admin | Todo lo anterior mas ABM de referentes, partidos, trabajos y personas. |
| superadmin | Todo lo anterior mas ABM de usuarios. Hay uno solo. |

El navbar muestra solo los items a los que el usuario tiene acceso.

auth.php expone tres funciones:

- verificar_sesion() — si no hay sesion activa, redirige al login.
- verificar_admin() — si el usuario no es admin ni superadmin, redirige con error.
- verificar_superadmin() — si el usuario no es superadmin, redirige con error.

---

## Modulos

| Modulo | Archivo | Acceso |
|---|---|---|
| Login | modulos/login/login.php | Publico |
| Buscador | modulos/buscador/buscador.php | Todos |
| Listados | modulos/listados/listados.php | Todos |
| Filtros | modulos/filtros/filtros.php | Todos |
| ABM Referentes | modulos/abm_referentes/abm_referentes.php | admin, superadmin |
| ABM Partidos | modulos/abm_partidos/abm_partidos.php | admin, superadmin |
| ABM Trabajos | modulos/abm_trabajos/abm_trabajos.php | admin, superadmin |
| ABM Personas | modulos/abm_personas/abm_personas.php | admin, superadmin |
| ABM Usuarios | modulos/abm_usuarios/abm_usuarios.php | superadmin |

### Buscador

Home del sistema. Input de busqueda por apellido o DNI. Resultados en tabla con columnas DNI, apellido, nombre, carrera, padron y boton Ver mas por fila. Si hay un unico resultado redirige directamente al perfil. Todo descargable en Excel.

### Listados

Tabla de listados disponibles con botones Ver y Descargar por fila. Al ver, el listado aparece paginado debajo (50 registros por pagina). Descargar genera el Excel completo sin paginacion.

Listados iniciales:

| Nombre | Fuente |
|---|---|
| Padron CD oficial | padron_cd |
| Padron CP oficial | padron_cp |
| Padron CD completo | vista_padron_cd |
| Padron CP completo | vista_padron_cp |

### Filtros

Padron y Auxiliares CP son obligatorios. Sin ambos elegidos no hay resultado.

**Combos disponibles:**

| Combo | Opciones | Notas |
|---|---|---|
| Padron | CD / CP | Obligatorio. |
| Auxiliares CP | SI / NO | Obligatorio. Se inhibe si no hay padron elegido. |
| Referente | Lista de activos + Con referentes | Opcional. |
| Partido | Lista de activos | Opcional. |
| Trabajo | Lista de activos | Opcional. |
| Carrera | Lista de carreras | Opcional. Se inhibe si padron = CP. |
| Eleccion | Todas las elecciones | Opcional. |
| Voto | Voto / No voto | Opcional. Aplica a la eleccion elegida. |

**Logica de combinacion segun Padron y Auxiliares CP:**

| Padron | Auxiliares CP | Resultado |
|---|---|---|
| CD | NO | Solo graduados CD |
| CD | SI | Graduados CD + auxiliares CP |
| CP | NO | Solo graduados CP sin auxiliares |
| CP | SI | Padron CP completo |

**Columnas de resultado:** siempre presentes las seis columnas de votos
(CD 2021, CD 2024, CP 2017, CP 2019, CP 2021, CP 2024) independientemente
del padron elegido. Las columnas sin dato para ese padron muestran NO.

Todo resultado es descargable en Excel.

### ABM Referentes, Partidos, Trabajos

Listado del catalogo con opciones editar y dar de baja logica. Formulario para agregar nuevo registro. Nunca se elimina un registro fisicamente.

Los registros con activo = 0 no aparecen en los combos de filtros ni en las vistas del padron.

### ABM Personas

Flujo de tres pasos: buscar persona por apellido o DNI, ver fila completa con datos actuales, editar vinculos (referentes, partido, trabajo) via combos de los catalogos correspondientes.

Solo aparecen en los combos los referentes, partidos y trabajos con activo = 1.

### ABM Usuarios

Solo superadmin. Listado con nombre, nivel y estado. Opciones de editar nivel, activar, desactivar. Formulario para crear nuevo usuario. Passwords con hash bcrypt. El superadmin no puede desactivarse a si mismo.

---

## Exportacion a Excel

Archivo includes/excel.php, funcion exportar_excel($resultado, $nombre_archivo).

Recibe el resultado de una query como array de filas asociativas y genera un .xlsx para descarga. Las columnas se construyen dinamicamente desde las claves del primer registro. Sin columnas hardcodeadas. Todo listado es siempre descargable en Excel.

---

## Convenciones de codigo

Ver [docs/convenciones.md](../docs/convenciones.md) para el detalle completo.

Resumen:

- PHP en UTF-8 sin BOM. Indentacion con 4 espacios. Variables en snake_case. Sin closing tag al final de archivos PHP puros.
- Siempre prepared statements con PDO. Nunca concatenacion de variables en queries.
- Parametros posicionales (?) en lugar de nombrados cuando una query combina resultados
  de vista_padron_cd y vista_padron_cp via UNION. Evita conflicto HY093 al hacer array_merge.
- Todo input del usuario se trata como potencialmente malicioso. htmlspecialchars() en todo output a pantalla.
- Todo bloque de logica no trivial va comentado. Los archivos empiezan con un comentario que indica su rol.

---

## Pendientes

- Cargar sede_laboral cuando el administrador tenga el listado tuneado.
- Validacion profunda de consistencia de datos migrados antes del pase a produccion.
- Modulo de Fiscalizacion (etapa futura, sistema separado con login propio).
