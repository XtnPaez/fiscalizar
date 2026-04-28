<?php
// Archivo temporal para generar el INSERT del superadmin
// BORRAR este archivo despues de usarlo

$usuario  = 'superadmin';
$password = 'Amanda2026';
$nivel    = 'superadmin';
$hash     = password_hash($password, PASSWORD_BCRYPT);

echo "Copiá este INSERT en phpMyAdmin:<br><br>";
echo "INSERT INTO usuarios (usuario, password, nivel, activo) VALUES (<br>";
echo "&nbsp;&nbsp;'" . $usuario . "',<br>";
echo "&nbsp;&nbsp;'" . $hash . "',<br>";
echo "&nbsp;&nbsp;'" . $nivel . "',<br>";
echo "&nbsp;&nbsp;1<br>";
echo ");";