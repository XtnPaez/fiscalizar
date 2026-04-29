# Consulta Padron

![Version](https://img.shields.io/badge/version-1.0-green)
![Estado](https://img.shields.io/badge/estado-completa-green)
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
| Aprobacion | Subdominio de fiscalizar.com.ar |
| Produccion | A definir al momento del pase |

Stack: PHP 8.1, MariaDB 10.6, Bootstrap 5, JavaScript nativo, PhpSpreadsheet via Composer. Sin frameworks PHP.

---

## Instalacion local

Ver [docs/instalacion.md](../docs/instalacion.md) para instrucciones completas paso a paso.

Resumen:

1. Clonar el repositorio en C:\xampp\htdocs\fiscalizar
2. Copiar config/db.example.php como config/db.php y completar con credenciales locales
3. Ejecutar composer install dentro de consulta_padron/
4. Importar fiscaliz_padron.sql en phpMyAdmin
5. Crear usuario superadmin en la tabla usuarios
6. Acceder desde http://localhost/fiscalizar/consulta_padron/

---

## Diseño visual

| Elemento | Valor |
|---|---|
| Framework CSS | Bootstrap 5 via CDN |
| Fuente | Inter via Google Fonts |
| Navbar y footer | #1a1a2e (azul muy oscuro) |
| Acento principal | #a6d900 (verde lima) |
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
│   ├── db.php
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
│   ├── buscador/
│   │   └── buscador.php
│   ├── padrones/
│   │   └── padrones.php
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
    ├── js/
    │   └── main.js
    └── img/
        └── logo.png
```

---

## Routing

No hay framework de routing. index.php recibe todos los requests y decide que modulo cargar segun el parametro mod en la URL.

```
/?mod=login
/?mod=buscador
/?mod=padrones
/?mod=filtros
/?mod=abm_referentes
/?mod=abm_partidos
/?mod=abm_trabajos
/?mod=abm_personas
/?mod=abm_usuarios
/?mod=logout
```

Sin parametro mod carga el buscador por defecto. Sin sesion activa redirige al login.

---

## Autenticacion

Sistema de login propio, independiente del modulo de Fiscalizacion. Los usuarios viven en la tabla usuarios de fiscaliz_padron.

| Nivel | Puede hacer |
|---|---|
| consulta | Buscador, padrones, filtros. Solo lectura. |
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
| Logout | modulos/logout/logout.php | Todos |
| Buscador | modulos/buscador/buscador.php | Todos |
| Padrones | modulos/padrones/padrones.php | Todos |
| Filtros | modulos/filtros/filtros.php | Todos |
| ABM Referentes | modulos/abm_referentes/abm_referentes.php | admin, superadmin |
| ABM Partidos | modulos/abm_partidos/abm_partidos.php | admin, superadmin |
| ABM Trabajos | modulos/abm_trabajos/abm_trabajos.php | admin, superadmin |
| ABM Personas | modulos/abm_personas/abm_personas.php | admin, superadmin |
| ABM Usuarios | modulos/abm_usuarios/abm_usuarios.php | superadmin |

### Buscador

Home del sistema. Input con sugerencias en tiempo real desde 2 caracteres. Busqueda por apellido, apellido nombre (separados por espacio) o DNI. Una sola fila por persona con flags de padron CD y CP. Perfil unificado con referentes, partido, trabajo, sede y todas las votaciones segun los padrones en que figura. Descarga Excel del listado y del perfil individual.

### Padrones

Tabla de padrones disponibles con botones Ver y Descargar. Al ver, el padron aparece paginado con buscador interno por apellido, apellido nombre o DNI. Scroll horizontal sincronizado arriba y abajo. Descargar genera el Excel completo respetando el buscador activo.

Padrones disponibles: CD oficial, CP oficial (sin columna auxiliar), CD completo, CP completo.

### Filtros

Combos en orden: Padron, Referente (incluye opcion Con Referentes), Partido, Trabajo, Auxiliar, Carrera, Eleccion, Voto. Resultado con perfil completo: referentes, partido, trabajo, sede y todas las votaciones CD y CP. Scroll horizontal sincronizado. Descarga Excel del resultado completo sin paginacion.

### ABM Referentes, Partidos, Trabajos

Buscador por nombre arriba del listado. Boton agregar que despliega formulario en collapse. Botones editar y dar de baja en la misma fila. Nunca se elimina un registro fisicamente.

### ABM Personas

Flujo de tres pasos: buscar persona por apellido, apellido nombre o DNI, ver perfil con datos actuales, editar vinculos (referentes, partido, trabajo) via combos de los catalogos correspondientes.

### ABM Usuarios

Solo superadmin. Listado con nombre, nivel y estado. Agregar usuario con hash bcrypt. Editar nivel y cambiar password. Activar y desactivar. El superadmin no puede desactivarse a si mismo.

---

## Exportacion a Excel

Archivo includes/excel.php, funcion exportar_excel($resultado, $nombre_archivo).

Recibe el resultado de una query como array de filas asociativas y genera un .xlsx para descarga. Las columnas se construyen dinamicamente desde las claves del primer registro. Sin columnas hardcodeadas. Todo listado es siempre descargable en Excel. Nombre del archivo: nombre-del-listado-YYYY-MM-DD.xlsx.

---

## Convenciones de codigo

Ver [docs/convenciones.md](../docs/convenciones.md) para el detalle completo.

Resumen:

- PHP en UTF-8 sin BOM. Indentacion con 4 espacios. Variables en snake_case. Sin closing tag al final de archivos PHP puros.
- Siempre prepared statements con PDO. Nunca concatenacion de variables en queries.
- Todo input del usuario se trata como potencialmente malicioso. htmlspecialchars() en todo output a pantalla.
- Todo bloque de logica no trivial va comentado. Los archivos empiezan con un comentario que indica su rol.
- Nunca consultar tablas directamente. Solo las vistas vista_padron_cd y vista_padron_cp.

---

## Pendientes para v2

- Cargar sede_laboral cuando el administrador tenga el listado tuneado.
- Validacion profunda de consistencia de datos migrados antes del pase a produccion.
- Cruces con tablas adicionales: st_siet_2026, st_ucr_caba_2026, st_ucr_pba_2024.
- Modulo de Fiscalizacion (etapa futura, sistema separado con login propio).
