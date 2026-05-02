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
| Usuarios | Referentes politicos, admin | Fiscales, admin electoral |
| Login | Propio, tabla usuarios | Propio, tabla a definir |
| Base de datos | fiscaliz_padron | fiscaliz_padron (compartida) |

Comparten la base pero no comparten codigo, usuarios ni sesiones.
Un usuario de Consulta Padron no tiene acceso a Fiscalizacion y viceversa.

---

## Que hace este modulo

- Registro de votos en tiempo real durante el dia de la eleccion.
- Gestion de mesas electorales y fiscales.
- Seguimiento del estado de votacion por mesa.
- Consulta rapida del padron durante la eleccion.

---

## Base de datos compartida

La base fiscaliz_padron ya tiene preparado el terreno para este modulo:

| Tabla | Rol en Fiscalizacion |
|---|---|
| personas | Nucleo de identidad. DNI como clave de cruce. |
| padron_cd | Padron habilitado para votar en CD. |
| padron_cp | Padron habilitado para votar en CP. |
| elecciones | Catalogo de elecciones. Campo activa identifica la eleccion en curso. |
| participacion_electoral | Recibe los registros del dia de la eleccion. |

Las tablas de mesas, fiscales y login propio de fiscalizacion
se agregan como tablas nuevas sin modificar las existentes.

---

## Entorno

| Etapa | Entorno |
|---|---|
| Desarrollo | Local — XAMPP en C:\xampp\htdocs\fiscalizar\fiscalizacion |
| Aprobacion | fiscalizar.com.ar |
| Produccion | fiscalizar.com.ar |

Stack: PHP 8.1, MariaDB 10.6, Bootstrap 5, JavaScript nativo. Sin frameworks PHP.

---

## Instalacion local

1. Clonar el repositorio en C:\xampp\htdocs\fiscalizar
2. Copiar config/db.example.php como config/db.php y completar con credenciales locales
3. Importar fiscaliz_padron.sql en phpMyAdmin si no esta ya importado
4. Acceder desde http://localhost/fiscalizar/fiscalizacion/

Ver docs/instalacion.md para instrucciones completas.

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
│   ├── mesa/
│   │   └── mesa.php
│   └── (a definir en diseño)
└── assets/
    ├── css/
    │   └── estilos.css
    └── js/
        └── main.js
```

---

## Routing

Mismo patron que Consulta Padron. index.php recibe todos los requests
y decide que modulo cargar segun el parametro mod en la URL.

```
/?mod=login
/?mod=mesa
```

Sin parametro mod carga la pantalla de login. Sin sesion activa redirige al login.

---

## Niveles de acceso

A definir en la etapa de diseño. El sistema de login es independiente
del de Consulta Padron. Los usuarios viven en una tabla propia de fiscalizacion.

---

## Estado del desarrollo

| Tarea | Estado |
|---|---|
| Estructura de carpetas | En curso |
| Diseño de tablas propias (mesas, fiscales) | Pendiente |
| Login propio | Pendiente |
| Modulo mesa | Pendiente |
| Registro de votos en tiempo real | Pendiente |

---

## Documentacion relacionada

- [docs/analisis_bbdd.md](../docs/analisis_bbdd.md) — Relevamiento de la base anterior.
- [docs/propuesta_bbdd.md](../docs/propuesta_bbdd.md) — Diseño del esquema compartido.
- [docs/convenciones.md](../docs/convenciones.md) — Convenciones de codigo del proyecto.
- [sql/estructura/fiscaliz_padron.sql](../sql/estructura/fiscaliz_padron.sql) — DDL completo de la base.
