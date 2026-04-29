# Instalacion local

![Entorno](https://img.shields.io/badge/entorno-XAMPP-F37623)
![OS](https://img.shields.io/badge/OS-Windows-0078D6)
![PHP](https://img.shields.io/badge/PHP-8.1-777BB4)
![MariaDB](https://img.shields.io/badge/MariaDB-10.6-4479A1)

Instrucciones para levantar Fiscalizar — Consulta Padron en entorno local con XAMPP sobre Windows.

---

## Requisitos

- XAMPP con PHP 8.1 y MariaDB 10.6
- Composer instalado globalmente
- Git instalado
- Acceso a phpMyAdmin en http://localhost/phpmyadmin

---

## Paso 1 — Clonar el repositorio

Abrir una terminal y ejecutar:

```
cd C:\xampp\htdocs
git clone https://github.com/XtnPaez/fiscalizar
```

La estructura quedara en C:\xampp\htdocs\fiscalizar\

---

## Paso 2 — Instalar dependencias PHP

Navegar a la carpeta de la aplicacion y ejecutar composer:

```
cd C:\xampp\htdocs\fiscalizar\consulta_padron
composer install
```

Esto crea la carpeta vendor/ con PhpSpreadsheet y sus dependencias. Puede tardar un minuto.

---

## Paso 3 — Configurar la base de datos

Copiar el archivo de ejemplo y completar con las credenciales locales:

```
cd C:\xampp\htdocs\fiscalizar\consulta_padron\config
copy db.example.php db.php
```

Editar db.php con las credenciales reales. En XAMPP el usuario por defecto es root sin password:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fiscaliz_padron');
define('DB_USER', 'root');
define('DB_PASS', '');
```

db.php esta en .gitignore y nunca se sube al repositorio.

---

## Paso 4 — Importar la base de datos

Abrir phpMyAdmin en http://localhost/phpmyadmin

Crear una base de datos llamada fiscaliz_padron con charset utf8mb4 y collation utf8mb4_spanish_ci.

Importar el archivo sql/estructura/fiscaliz_padron.sql desde la pestaña Importar.

Si el archivo supera el limite de subida de phpMyAdmin, usar la linea de comandos:

```
mysql -u root fiscaliz_padron < sql/estructura/fiscaliz_padron.sql
```

---

## Paso 5 — Crear el usuario superadmin

Antes de acceder al sistema hay que crear al menos un usuario. Crear un archivo temporal generar_usuario.php en consulta_padron/ con este contenido:

```php
<?php
require_once 'config/db.php';
$hash = password_hash('tu_password_aqui', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, nivel, activo) VALUES ('superadmin', :hash, 'superadmin', 1)");
$stmt->execute([':hash' => $hash]);
echo 'Usuario creado.';
```

Acceder desde http://localhost/fiscalizar/consulta_padron/generar_usuario.php

Borrar el archivo inmediatamente despues de usarlo.

---

## Paso 6 — Verificar la conexion

Crear un archivo temporal test_conexion.php en consulta_padron/:

```php
<?php
require_once 'config/db.php';
echo "Conexion exitosa. Base de datos: " . DB_NAME;
```

Acceder desde http://localhost/fiscalizar/consulta_padron/test_conexion.php

Borrar el archivo antes de continuar.

---

## Paso 7 — Acceder a la aplicacion

```
http://localhost/fiscalizar/consulta_padron/
```

---

## Notas

**vendor/ y composer.lock** no se versionan. Cada desarrollador ejecuta composer install en su entorno.

**db.php** no se versiona. Cada desarrollador crea el suyo a partir de db.example.php.

---

## Problemas frecuentes

**Error de conexion a la base de datos**

Verificar que XAMPP este corriendo con Apache y MySQL activos. Verificar las credenciales en db.php. Para ver el error real, reemplazar temporalmente el die() del catch en db.php con die($e->getMessage()) y volver a cargar. Revertir el cambio antes de continuar.

**composer: comando no reconocido**

Composer no esta instalado o no esta en el PATH. Descargar el instalador desde https://getcomposer.org/download/ y reinstalar.

**La pagina no carga**

Verificar que la URL sea exactamente http://localhost/fiscalizar/consulta_padron/ y que Apache este corriendo en XAMPP.

**El archivo SQL es muy grande para phpMyAdmin**

Usar la importacion por linea de comandos descripta en el Paso 4.
