<?php
// generar_usuario_fiscal.php
// Crea el usuario superadmin en la tabla usuarios_fiscal.
// Ejecutar UNA SOLA VEZ desde el browser despues del reset_total.sql.
// BORRAR INMEDIATAMENTE despues de usar.
//
// Acceso: http://fiscalizar.com.ar/generar_usuario_fiscal.php
// (o http://localhost/fiscalizar/fiscalizacion/generar_usuario_fiscal.php en local)

require_once 'config/db.php';

$usuario  = 'superadmin';       // <- cambiar si se quiere otro nombre
$password = 'Amanda2026';     // <- cambiar por el password real antes de ejecutar
$nivel    = 'superadmin';

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    INSERT INTO usuarios_fiscal (usuario, password, nivel, tipo, activo)
    VALUES (?, ?, ?, NULL, 1)
");
$stmt->execute([$usuario, $hash, $nivel]);

echo 'Usuario <strong>' . htmlspecialchars($usuario) . '</strong> creado correctamente.';
echo '<br>Borra este archivo del servidor inmediatamente.';
