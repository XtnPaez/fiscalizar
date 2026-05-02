# Convenciones de codigo

![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![MariaDB](https://img.shields.io/badge/MariaDB-10.6-4479A1)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3)

Convenciones que aplican a todo el codigo del proyecto Fiscalizar.
Validas tanto para el desarrollo Consulta Padron como para Fiscalizacion.

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
- Nunca consultar tablas directamente. Solo las vistas predefinidas de cada desarrollo.
- Toda query va en su propio bloque con comentario que indica que hace.

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

## HTML y frontend

- HTML generado desde PHP.
- Bootstrap 5 para estructura y componentes, cargado desde CDN.
- Estilos propios solo en assets/css/estilos.css. Nunca estilos inline salvo casos excepcionales justificados.
- JavaScript propio solo en assets/js/main.js.
- Sin frameworks JavaScript.

---

## Estructura de archivos

- Un modulo por carpeta dentro de modulos/.
- Un solo archivo PHP por modulo con el mismo nombre que la carpeta.
- Includes compartidos en includes/.
- Configuracion en config/.
- Todo recurso estatico en assets/.

El termino modulo refiere a una unidad funcional dentro de un desarrollo
(por ejemplo: buscador, login, filtros dentro de consulta_padron).
No es sinonimo de desarrollo. Los desarrollos son consulta_padron y fiscalizacion,
y cada uno vive en su propia carpeta dentro del repositorio.

---

## Exportacion a Excel

- Toda exportacion pasa por la funcion exportar_excel() en includes/excel.php.
- Las columnas se construyen dinamicamente desde las claves del primer registro del resultado.
- Nunca columnas hardcodeadas en la funcion de exportacion.
- El nombre del archivo descargado sigue el patron: nombre-del-listado-YYYY-MM-DD.xlsx

---

## Control de versiones

### Estructura de ramas

El repositorio tiene tres ramas de larga duracion:

| Rama | Rol |
|---|---|
| main | Produccion. Solo recibe merges aprobados. Nunca se desarrolla directamente aca. |
| consulta-padron | Desarrollo activo de consulta_padron/. |
| fiscalizacion | Desarrollo activo de fiscalizacion/. |

No hay subramas por modulo. Cada rama de desarrollo agrupa todos los commits
de su desarrollo hasta que ese desarrollo este aprobado y listo para mergear a main.

### Disciplina por rama

- En la rama consulta-padron se tocan archivos de consulta_padron/, docs/ y sql/.
- En la rama fiscalizacion se tocan archivos de fiscalizacion/, docs/ y sql/.
- Nunca se desarrolla en main.
- Nunca se toca la carpeta del otro desarrollo desde la rama propia.

### Commits

- En español, en imperativo, descriptivos.
- Ejemplo correcto: "Agrega modulo de login con control de sesion"
- Ejemplo correcto: "Corrige validacion de DNI en buscador"
- Ejemplo incorrecto: "cambios", "fix", "update"

### Archivos que nunca se commitean

- db.php (credenciales de base de datos)
- vendor/ (dependencias de Composer)

Antes de cada push verificar que .gitignore este cumpliendo su funcion.

---

## Niveles de acceso

### Consulta Padron

| Nivel | Modulos habilitados |
|---|---|
| consulta | login, buscador, listados, filtros |
| admin | todo lo anterior mas abm_referentes, abm_partidos, abm_trabajos, abm_personas |
| superadmin | todo lo anterior mas abm_usuarios |

Todo modulo llama a verificar_sesion() al inicio.
Los modulos ABM llaman ademas a verificar_admin().
El modulo abm_usuarios llama a verificar_superadmin().

### Fiscalizacion

Niveles de acceso a definir en la etapa de diseño de Fiscalizacion.
El sistema de login de Fiscalizacion es independiente del de Consulta Padron.
Los usuarios de un desarrollo no tienen acceso al otro.
