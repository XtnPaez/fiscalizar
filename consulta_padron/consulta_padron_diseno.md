# Consulta Padrón — Diseño del desarrollo

**Proyecto:** Fiscalizar  
**Fecha:** Marzo 2026  
**Etapa:** Consulta Padrón — diseño previo al desarrollo  

---

## 1. Contexto

Consulta Padrón es la primera etapa del sistema Fiscalizar. Es una aplicación web en PHP puro que permite consultar, filtrar y exportar los padrones electorales de la Facultad de Ciencias Sociales (UBA). Se desarrolla en entorno local y se despliega en un subdominio para aprobación antes de pasar a producción.

La aplicación consume exclusivamente las vistas `vista_padron_cd` y `vista_padron_cp` de la base `fiscaliz_padron`. Nunca consulta tablas directamente.

---

## 2. Entorno de desarrollo y deploy

| Etapa | Entorno |
|---|---|
| Desarrollo | Local |
| Aprobación | Subdominio de fiscalizar.com.ar |
| Producción | A definir al momento del pase |

Stack: PHP 8.1, MariaDB 10.6, HTML + CSS + JavaScript nativo. Sin frameworks.

---

## 3. Estructura de carpetas

```
consulta_padron/
├── index.php                   # Entry point. Maneja el routing y la sesion.
├── config/
│   └── db.php                  # Conexion a la base de datos. Un solo lugar.
├── includes/
│   ├── auth.php                # Funciones de autenticacion y control de sesion.
│   ├── funciones.php           # Funciones utilitarias generales.
│   └── excel.php               # Funcion de exportacion a Excel.
├── modulos/
│   ├── login/
│   │   ├── login.php           # Formulario de login.
│   │   └── logout.php          # Cierre de sesion.
│   ├── buscador/
│   │   └── buscador.php        # Busqueda por apellido o DNI. Vista de resultados y perfil.
│   ├── listados/
│   │   └── listados.php        # Listados predefinidos paginados con boton de descarga.
│   ├── filtros/
│   │   └── filtros.php         # Filtros combinados. Genera listado y permite descarga.
│   ├── abm_referentes/
│   │   └── abm_referentes.php  # ABM del catalogo de referentes.
│   ├── abm_partidos/
│   │   └── abm_partidos.php    # ABM del catalogo de partidos.
│   ├── abm_trabajos/
│   │   └── abm_trabajos.php    # ABM del catalogo de trabajos.
│   └── abm_personas/
│       └── abm_personas.php    # Busqueda de persona y edicion de vinculos.
└── assets/
    ├── css/
    │   └── estilos.css         # Hoja de estilos unica.
    └── js/
        └── main.js             # JavaScript general.
```

---

## 4. Routing

No hay framework de routing. `index.php` recibe todos los requests y decide qué módulo cargar según el parámetro `mod` en la URL.

```
fiscalizar.com.ar/consulta_padron/?mod=buscador
fiscalizar.com.ar/consulta_padron/?mod=listados
fiscalizar.com.ar/consulta_padron/?mod=filtros
fiscalizar.com.ar/consulta_padron/?mod=abm_referentes
```

Si no hay parámetro `mod`, carga el buscador por defecto. Si el usuario no está autenticado, redirige al login.

---

## 5. Autenticación y sesiones

Sistema de login propio, independiente del módulo de Fiscalización. Los usuarios viven en una tabla `usuarios` de la misma base `fiscaliz_padron`.

### Tabla `usuarios`

```sql
CREATE TABLE `usuarios` (
    `id`        INT             NOT NULL AUTO_INCREMENT,
    `usuario`   VARCHAR(60)     NOT NULL,
    `password`  VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt',
    `nivel`     ENUM('consulta','admin') NOT NULL DEFAULT 'consulta',
    `activo`    TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_usuario` (`usuario`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_spanish_ci
  COMMENT='Usuarios del modulo Consulta Padron.';
```

### Niveles de acceso

| Nivel | Puede hacer |
|---|---|
| `consulta` | Buscador, listados, filtros. Solo lectura. |
| `admin` | Todo lo anterior más ABM de referentes, partidos, trabajos y personas. |

### Control de sesión

`auth.php` expone dos funciones:
- `verificar_sesion()` — si no hay sesión activa, redirige al login.
- `verificar_admin()` — si el usuario no es admin, redirige al inicio con mensaje de error.

Todo módulo llama a `verificar_sesion()` al inicio. Los módulos ABM además llaman a `verificar_admin()`.

---

## 6. Conexión a la base de datos

Un único archivo `config/db.php` establece la conexión. Todos los módulos lo incluyen. Nunca se repite la cadena de conexión.

```php
<?php
// config/db.php
// Conexion unica a fiscaliz_padron.
// Todos los modulos incluyen este archivo. No se duplica la conexion.

$host     = 'localhost';
$dbname   = 'fiscaliz_padron';
$usuario  = 'fiscaliz_dev';
$password = 'XXXXXXXX'; // Completar antes de deploy

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $usuario,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Error de conexion: ' . $e->getMessage());
}
```

Se usa PDO en todos los módulos. Sin mysqli. Sin concatenación de variables en queries: siempre prepared statements.

---

## 7. Módulos

---

### 7.1 Login

**Archivo:** `modulos/login/login.php`  
**Acceso:** público (sin sesión requerida)

Formulario con campos usuario y password. Al autenticar, guarda en `$_SESSION`: `dni_usuario`, `nivel`, `nombre`. Redirige al buscador.

---

### 7.2 Buscador

**Archivo:** `modulos/buscador/buscador.php`  
**Acceso:** `consulta` y `admin`

**Pantalla inicial:** input de búsqueda. El usuario escribe apellido o DNI y presiona buscar.

**Resultados:** tabla con las columnas DNI, apellido, nombre, carrera, padrón (CD / CP / ambos) y botón Ver más por fila. Si no hay resultados, mensaje claro. Si hay un único resultado, redirige directamente al perfil.

**Perfil:** página con todos los datos disponibles para ese DNI: apellido, nombre, carrera, referentes (hasta 3), partido, trabajo, sede laboral, y participación histórica en cada elección. Los datos que no existen se muestran como "Sin dato" o "No votó" según corresponda.

**Query de búsqueda:** consulta `personas` y joinea con `padron_cd` y `padron_cp` para determinar en qué padrón figura. El perfil se arma consultando `vista_padron_cd` y/o `vista_padron_cp` según corresponda.

---

### 7.3 Listados

**Archivo:** `modulos/listados/listados.php`  
**Acceso:** `consulta` y `admin`

Pantalla con botones o cards, uno por listado disponible. Al hacer clic en uno, se muestra el listado paginado en pantalla con un botón de descarga en Excel.

**Listados iniciales:**

| Nombre | Fuente | Descripción |
|---|---|---|
| Padrón CD oficial | `padron_cd` | Solo DNI, apellido, nombre, sigla. |
| Padrón CP oficial | `padron_cp` | Solo DNI, apellido, nombre, auxiliar. |
| Padrón CD completo | `vista_padron_cd` | Con referentes, partido, trabajo, votos. |
| Padrón CP completo | `vista_padron_cp` | Ídem para CP. |

Se pueden agregar listados nuevos sin modificar código: alcanza con agregar una entrada en el array de configuración de listados dentro del módulo.

**Paginación:** 50 registros por página. Navegación con anterior / siguiente y número de página actual.

**Exportación a Excel:** descarga el listado completo (no solo la página visible). El PHP construye el Excel dinámicamente desde las columnas del resultado de la vista. No hay columnas hardcodeadas.

---

### 7.4 Filtros

**Archivo:** `modulos/filtros/filtros.php`  
**Acceso:** `consulta` y `admin`

Pantalla con combos de filtro. Cada combo no seleccionado equivale a "todos". Al presionar Generar listado, muestra el resultado paginado con botón de descarga en Excel.

**Filtros disponibles:**

| Filtro | Fuente del combo |
|---|---|
| Padrón | CD / CP (fijo) |
| Carrera | `carreras` |
| Referente | `referentes` |
| Partido | `partidos` |
| Trabajo | `trabajos` |
| Votó en elección | `elecciones` |

La query se construye dinámicamente en base a los filtros seleccionados, siempre contra la vista correspondiente.

---

### 7.5 ABM Referentes

**Archivo:** `modulos/abm_referentes/abm_referentes.php`  
**Acceso:** solo `admin`

Listado de referentes con opciones de editar y dar de baja lógica (campo `activo`). Formulario para agregar nuevo referente con campos apellido y nombre separados. No se elimina ningún registro: la baja es lógica.

---

### 7.6 ABM Partidos

**Archivo:** `modulos/abm_partidos/abm_partidos.php`  
**Acceso:** solo `admin`

Misma lógica que ABM Referentes aplicada al catálogo de partidos.

---

### 7.7 ABM Trabajos

**Archivo:** `modulos/abm_trabajos/abm_trabajos.php`  
**Acceso:** solo `admin`

Misma lógica que ABM Referentes aplicada al catálogo de trabajos.

---

### 7.8 ABM Personas

**Archivo:** `modulos/abm_personas/abm_personas.php`  
**Acceso:** solo `admin`

**Flujo:**
1. El admin busca una persona por apellido o DNI.
2. Se muestra su perfil con los datos actuales: referentes (hasta 3), partido, trabajo.
3. Al lado de cada dato hay un combo que permite seleccionar el nuevo valor desde el catálogo correspondiente.
4. El admin selecciona y confirma. El sistema actualiza `referentes_graduado`, `persona_partido` o `persona_trabajo` según corresponda.

**Regla:** si el dato que se necesita no existe en el catálogo, el admin debe ir primero al ABM correspondiente a crearlo y luego volver a esta pantalla.

---

## 8. Exportación a Excel

**Archivo:** `includes/excel.php`  
**Función:** `exportar_excel($resultado, $nombre_archivo)`

Recibe el resultado de una query (array de filas asociativas) y genera un archivo `.xlsx` para descarga. Las columnas se construyen dinámicamente desde las claves del primer registro. No hay columnas hardcodeadas. Usa la librería PhpSpreadsheet instalada via Composer.

---

## 9. Convenciones de código

- **PHP:** archivos en UTF-8 sin BOM. Indentación con 4 espacios. Variables en snake_case. Sin closing tag `?>` al final de archivos PHP puros.
- **SQL:** siempre prepared statements con PDO. Nunca concatenación de variables en queries. Nombres de tablas y columnas en minúsculas con guión bajo.
- **HTML:** generado desde PHP. Sin frameworks CSS por ahora. Clase de cada elemento debe indicar su módulo y función.
- **Comentarios:** todo bloque de lógica no trivial va comentado. Los archivos empiezan con un comentario que indica su rol.
- **Seguridad:** todo input del usuario se trata como potencialmente malicioso. Prepared statements en todas las queries. `htmlspecialchars()` en todo output de datos del usuario a pantalla.

---

## 10. Lo que este documento NO define todavía

- Diseño visual detallado (colores, tipografía, layout exacto de cada pantalla).
- Estructura de la tabla `usuarios` en producción (cantidad de usuarios, contraseñas).
- Listados adicionales más allá de los cuatro iniciales.
- Módulo de Fiscalización (etapa futura, sistema separado).

---

## Resumen

Consulta Padrón es una aplicación PHP de una sola base (`fiscaliz_padron`), sin frameworks, con routing simple por parámetro GET, login propio con dos niveles de acceso, y ocho módulos: buscador, listados, filtros, tres ABM de catálogos y un ABM de personas. Toda consulta de datos va contra las vistas de la base. La exportación a Excel es dinámica y no requiere modificar código cuando cambia la estructura de una vista. El desarrollo arranca en entorno local y se despliega en subdominio para aprobación antes de pasar a producción.
