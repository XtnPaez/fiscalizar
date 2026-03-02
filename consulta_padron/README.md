# Consulta Padrón

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

Stack: PHP 8.1, MariaDB 10.6, Bootstrap 5, HTML + JavaScript nativo. Sin frameworks PHP.

---

## 3. Diseño visual

**Framework CSS:** Bootstrap 5 cargado desde CDN.  
**Fuente:** Inter (Google Fonts). Moderna, legible, ideal para pantallas de datos.  
**Esquema de color:**

| Elemento | Color |
|---|---|
| Navbar y footer | `#1a1a2e` (azul muy oscuro) |
| Acento principal | `#4f8ef7` (azul medio) |
| Fondo de página | `#f0f2f5` (gris claro) |
| Texto principal | `#1a1a2e` |
| Texto secundario | `#4a5568` |

**Principio de diseño:** el sistema es una herramienta de trabajo. Todo lo que se ve en pantalla es un listado tabular, igual a lo que se va a descargar en Excel. Sin fichas, sin cajas decorativas, sin cards. La información al frente.

---

## 4. Estructura de carpetas

```
consulta_padron/
├── README.md                   # Este archivo
├── index.php                   # Entry point. Maneja el routing y la sesion.
├── config/
│   └── db.php                  # Conexion a la base de datos. Un solo lugar.
├── includes/
│   ├── auth.php                # Funciones de autenticacion y control de sesion.
│   ├── navbar.php              # Navbar superior. Se incluye en todas las paginas.
│   ├── footer.php              # Footer. Se incluye en todas las paginas.
│   ├── funciones.php           # Funciones utilitarias generales.
│   └── excel.php               # Funcion de exportacion a Excel.
├── modulos/
│   ├── login/
│   │   └── login.php           # Formulario de login y cierre de sesion.
│   ├── buscador/
│   │   └── buscador.php        # Busqueda por apellido o DNI. Resultados y perfil.
│   ├── listados/
│   │   └── listados.php        # Listados predefinidos paginados con descarga Excel.
│   ├── filtros/
│   │   └── filtros.php         # Filtros combinados con descarga Excel.
│   ├── abm_referentes/
│   │   └── abm_referentes.php  # ABM del catalogo de referentes.
│   ├── abm_partidos/
│   │   └── abm_partidos.php    # ABM del catalogo de partidos.
│   ├── abm_trabajos/
│   │   └── abm_trabajos.php    # ABM del catalogo de trabajos.
│   ├── abm_personas/
│   │   └── abm_personas.php    # Busqueda de persona y edicion de vinculos.
│   └── abm_usuarios/
│       └── abm_usuarios.php    # ABM de usuarios. Solo superadmin.
└── assets/
    ├── css/
    │   └── estilos.css         # Estilos propios sobre Bootstrap.
    └── js/
        └── main.js             # JavaScript general.
```

---

## 5. Routing

No hay framework de routing. `index.php` recibe todos los requests y decide qué módulo cargar según el parámetro `mod` en la URL.

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

Si no hay parámetro `mod`, carga el buscador por defecto. Si el usuario no está autenticado, redirige al login.

---

## 6. Autenticación y sesiones

Sistema de login propio, independiente del módulo de Fiscalización. Los usuarios viven en una tabla `usuarios` de la misma base `fiscaliz_padron`.

### Tabla `usuarios`

```sql
CREATE TABLE `usuarios` (
    `id`        INT             NOT NULL AUTO_INCREMENT,
    `usuario`   VARCHAR(60)     NOT NULL,
    `password`  VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt',
    `nivel`     ENUM('consulta','admin','superadmin') NOT NULL DEFAULT 'consulta',
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
| `superadmin` | Todo lo anterior más ABM de usuarios. Hay uno solo, creado directamente en la base. |

### Navbar según nivel

El navbar muestra solo los ítems a los que el usuario tiene acceso. El dropdown ABM no aparece para `consulta`. El ítem Usuarios dentro del dropdown solo aparece para `superadmin`.

### Control de sesión

`auth.php` expone tres funciones:
- `verificar_sesion()` — si no hay sesión activa, redirige al login.
- `verificar_admin()` — si el usuario no es `admin` ni `superadmin`, redirige con error.
- `verificar_superadmin()` — si el usuario no es `superadmin`, redirige con error.

Todo módulo llama a `verificar_sesion()` al inicio. Los módulos ABM además llaman a `verificar_admin()`. El módulo ABM Usuarios llama a `verificar_superadmin()`.

---

## 7. Conexión a la base de datos

Un único archivo `config/db.php` establece la conexión con PDO. Todos los módulos lo incluyen. Nunca se repite la cadena de conexión. Siempre prepared statements. Nunca concatenación de variables en queries.

---

## 8. Navbar y footer

Archivos separados incluidos en todas las páginas. Si cambia el logo, un ítem del menú o el texto del footer, se toca un solo archivo.

**Navbar:** fondo `#1a1a2e`, ítems de navegación directos, dropdown ABM condicional según nivel, usuario activo y botón Salir a la derecha.

**Footer:** fondo `#1a1a2e`, texto mínimo: nombre del sistema, institución, año.

---

## 9. Módulos

---

### 9.1 Login

**Archivo:** `modulos/login/login.php`  
**Acceso:** público

Formulario con campos usuario y password. Al autenticar guarda en `$_SESSION`: `id`, `usuario`, `nivel`. Redirige al buscador. El cierre de sesión destruye la sesión y redirige al login.

---

### 9.2 Buscador

**Archivo:** `modulos/buscador/buscador.php`  
**Acceso:** todos los niveles

**Home del sistema.** Input de búsqueda centrado en pantalla. El usuario escribe apellido o DNI y presiona Buscar.

Tres accesos rápidos debajo del buscador: Padrón CD completo, Padrón CP completo, Filtros avanzados.

**Resultados:** tabla con columnas DNI, apellido, nombre, carrera, padrón (CD / CP / ambos) y botón Ver más por fila. Siempre descargable en Excel, aunque sea un solo resultado.

**Perfil (Ver más):** listado de una sola fila con todas las columnas disponibles para ese DNI: referentes, partido, trabajo, sede laboral, participación histórica. Mismo formato tabular que cualquier otro listado. Descargable en Excel.

Si hay un único resultado en la búsqueda, redirige directamente al perfil.

---

### 9.3 Listados

**Archivo:** `modulos/listados/listados.php`  
**Acceso:** todos los niveles

Página con tabla de listados disponibles. Nombre, descripción breve y botones Ver y Descargar por fila. Al hacer clic en Ver, el listado se muestra paginado debajo (50 registros por página). Descargar genera el Excel completo sin paginación.

**Listados iniciales:**

| Nombre | Fuente | Descripción |
|---|---|---|
| Padrón CD oficial | `padron_cd` | DNI, apellido, nombre, sigla. |
| Padrón CP oficial | `padron_cp` | DNI, apellido, nombre, auxiliar. |
| Padrón CD completo | `vista_padron_cd` | Con referentes, partido, trabajo, votos. |
| Padrón CP completo | `vista_padron_cp` | Ídem para CP. |

Los listados se definen en un array de configuración dentro del módulo. Agregar uno nuevo no requiere modificar código fuera de ese array.

---

### 9.4 Filtros

**Archivo:** `modulos/filtros/filtros.php`  
**Acceso:** todos los niveles

Fila de combos en la parte superior. Cada combo no seleccionado equivale a "todos". Botón Generar listado. El resultado aparece debajo en formato tabular paginado con botón de descarga Excel.

**Filtros disponibles:**

| Filtro | Fuente del combo |
|---|---|
| Padrón | CD / CP (fijo) |
| Carrera | `carreras` |
| Referente | `referentes` |
| Partido | `partidos` |
| Trabajo | `trabajos` |
| Votó en elección | `elecciones` |

La query se construye dinámicamente según los filtros seleccionados, siempre contra la vista correspondiente.

---

### 9.5 ABM Referentes

**Archivo:** `modulos/abm_referentes/abm_referentes.php`  
**Acceso:** `admin` y `superadmin`

Listado de referentes con opciones editar y dar de baja lógica (campo `activo`). Formulario para agregar nuevo referente con apellido y nombre separados. No se elimina ningún registro físicamente.

---

### 9.6 ABM Partidos

**Archivo:** `modulos/abm_partidos/abm_partidos.php`  
**Acceso:** `admin` y `superadmin`

Misma lógica que ABM Referentes aplicada al catálogo de partidos.

---

### 9.7 ABM Trabajos

**Archivo:** `modulos/abm_trabajos/abm_trabajos.php`  
**Acceso:** `admin` y `superadmin`

Misma lógica que ABM Referentes aplicada al catálogo de trabajos.

---

### 9.8 ABM Personas

**Archivo:** `modulos/abm_personas/abm_personas.php`  
**Acceso:** `admin` y `superadmin`

**Flujo:**
1. El admin busca una persona por apellido o DNI.
2. Se muestra su fila completa en formato tabular con los datos actuales.
3. Al lado de cada campo editable (referentes, partido, trabajo) hay un combo con los valores del catálogo correspondiente.
4. El admin selecciona y confirma. El sistema actualiza `referentes_graduado`, `persona_partido` o `persona_trabajo`.

Si el valor necesario no existe en el catálogo, el admin va primero al ABM correspondiente a crearlo y luego vuelve a esta pantalla.

---

### 9.9 ABM Usuarios

**Archivo:** `modulos/abm_usuarios/abm_usuarios.php`  
**Acceso:** solo `superadmin`

Listado de usuarios con nombre, nivel y estado (activo/inactivo). Opciones de editar nivel, activar, desactivar. Formulario para crear nuevo usuario con usuario, password y nivel. Las contraseñas se guardan con hash bcrypt. El superadmin no puede desactivarse a sí mismo.

---

## 10. Exportación a Excel

**Archivo:** `includes/excel.php`  
**Función:** `exportar_excel($resultado, $nombre_archivo)`

Recibe el resultado de una query (array de filas asociativas) y genera un archivo `.xlsx` para descarga. Las columnas se construyen dinámicamente desde las claves del primer registro. Sin columnas hardcodeadas. Librería: PhpSpreadsheet via Composer.

Todo listado es siempre descargable en Excel, incluyendo resultados de búsqueda de un solo graduado.

---

## 11. Convenciones de código

- **PHP:** archivos en UTF-8 sin BOM. Indentación con 4 espacios. Variables en snake_case. Sin closing tag `?>` al final de archivos PHP puros.
- **SQL:** siempre prepared statements con PDO. Nunca concatenación de variables en queries. Nombres de tablas y columnas en minúsculas con guión bajo.
- **HTML:** generado desde PHP. Bootstrap 5 para estructura y componentes. Estilos propios solo en `assets/css/estilos.css`.
- **Comentarios:** todo bloque de lógica no trivial va comentado. Los archivos empiezan con un comentario que indica su rol.
- **Seguridad:** todo input del usuario se trata como potencialmente malicioso. Prepared statements en todas las queries. `htmlspecialchars()` en todo output a pantalla.

---

## 12. Lo que este documento NO define todavía

- Diseño visual detallado de cada pantalla más allá del home.
- Cantidad de usuarios iniciales y sus credenciales.
- Listados adicionales más allá de los cuatro iniciales.
- Módulo de Fiscalización (etapa futura, sistema separado con login propio).

---

## Resumen

Consulta Padrón es una aplicación PHP sin frameworks, con routing simple por parámetro GET, login propio con tres niveles de acceso (consulta, admin, superadmin), y nueve módulos: buscador (home del sistema), listados, filtros, tres ABM de catálogos, ABM de personas y ABM de usuarios. Todo lo que se muestra en pantalla es un listado tabular descargable en Excel. Navbar y footer son archivos separados incluidos en todas las páginas. Toda consulta de datos va contra las vistas de la base. El desarrollo arranca en entorno local y se despliega en subdominio para aprobación antes de pasar a producción.
