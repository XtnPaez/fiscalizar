# Convenciones de codigo

![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![MariaDB](https://img.shields.io/badge/MariaDB-10.6-4479A1)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3)

Convenciones que aplican a todo el codigo del proyecto Fiscalizar — Consulta Padron.

---

## PHP

- Archivos en UTF-8 sin BOM.
- Indentacion con 4 espacios. Sin tabs.
- Variables y funciones en snake_case.
- Clases en PascalCase (no se usan en esta etapa).
- Sin closing tag ?> al final de archivos PHP puros.
- Cada archivo empieza con un comentario de bloque que indica su rol dentro del sistema.
- Todo bloque de logica no trivial va comentado inline.

Ejemplo de encabezado de archivo:

```php
<?php
// modulos/buscador/buscador.php
// Modulo de busqueda por apellido o DNI.
// Acceso: todos los niveles autenticados.
// Consulta: vista_padron_cd y vista_padron_cp.
```

---

## Base de datos

- Nombres de tablas y columnas en minusculas con guion bajo.
- Siempre prepared statements con PDO. Nunca concatenacion de variables en queries.
- Nunca consultar tablas directamente. Solo las vistas vista_padron_cd y vista_padron_cp.
- Toda query va en su propio bloque con comentario que indica que hace.
- Cuando una query usa el mismo parametro en dos partes de un UNION, usar nombres distintos (:dni_cd y :dni_cp) para evitar el error HY093 de PDO.

Ejemplo de query correcta:

```php
// Buscar persona por DNI en el padron CD
$stmt = $pdo->prepare("
    SELECT dni, apellido, nombre, carrera
    FROM vista_padron_cd
    WHERE dni = :dni
");
$stmt->execute([':dni' => $dni]);
$resultado = $stmt->fetchAll();
```

Ejemplo de query incorrecta (nunca hacer esto):

```php
$resultado = $pdo->query("SELECT * FROM vista_padron_cd WHERE dni = " . $_GET['dni']);
```

---

## Seguridad

- Todo input del usuario se trata como potencialmente malicioso.
- Prepared statements en todas las queries sin excepcion.
- htmlspecialchars() en todo output a pantalla.
- Nunca mostrar mensajes de error reales de PHP o PDO al usuario final.
- Las passwords se guardan siempre con hash bcrypt via password_hash().

Ejemplo de output seguro:

```php
echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8');
```

---

## Manejo de errores

- Todo modulo se carga desde index.php dentro de un try/catch global.
- Cualquier excepcion no manejada redirige a modulos/error/error.php sin romper la sesion.
- El modulo error muestra un mensaje amigable y un boton para volver al inicio.
- Para errores esperados dentro de un modulo, usar $mensaje_error antes de hacer require del modulo error.

---

## HTML y frontend

- HTML generado desde PHP.
- Bootstrap 5 para estructura y componentes, cargado desde CDN.
- Estilos propios solo en assets/css/estilos.css. Nunca estilos inline salvo casos excepcionales justificados.
- JavaScript propio solo en assets/js/main.js. El JS especifico de cada modulo va al pie del mismo archivo PHP del modulo.
- Sin frameworks JavaScript.
- Scroll horizontal sincronizado arriba y abajo en tablas anchas via JS vanilla inline en el modulo.

---

## Estructura de archivos

- Un modulo por carpeta dentro de modulos/.
- Un solo archivo PHP por modulo con el mismo nombre que la carpeta.
- Includes compartidos en includes/.
- Configuracion en config/.
- Todo recurso estatico en assets/.
- Imagenes en assets/img/.

---

## Exportacion a Excel

- Toda exportacion pasa por la funcion exportar_excel() en includes/excel.php.
- Las columnas se construyen dinamicamente desde las claves del primer registro del resultado.
- Nunca columnas hardcodeadas en la funcion de exportacion.
- El nombre del archivo descargado sigue el patron: nombre-del-listado-YYYY-MM-DD.xlsx
- La exportacion debe ejecutarse antes de cualquier output HTML para no romper los headers.

---

## Control de versiones

- Una rama por funcionalidad o modulo. Merge a main cuando el modulo esta completo y probado.
- Commits en español, en imperativo, descriptivos. Ejemplo: "Agrega modulo de login con control de sesion".
- db.php nunca se commitea. vendor/ nunca se commitea.
- Antes de cada push verificar que .gitignore este cumpliendo su funcion.

---

## Niveles de acceso — recordatorio rapido

| Nivel | Modulos habilitados |
|---|---|
| consulta | login, buscador, padrones, filtros |
| admin | todo lo anterior mas abm_referentes, abm_partidos, abm_trabajos, abm_personas |
| superadmin | todo lo anterior mas abm_usuarios |

Todo modulo llama a verificar_sesion() al inicio. Los modulos ABM llaman ademas a verificar_admin(). El modulo abm_usuarios llama a verificar_superadmin().
