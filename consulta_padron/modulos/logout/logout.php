<?php
// modulos/logout/logout.php
// Cierra la sesion activa y redirige al login.
// Acceso: todos los niveles autenticados.

session_start();

// Destruir todos los datos de sesion
$_SESSION = [];
session_destroy();

// Redirigir al login
header('Location: index.php?mod=login');
exit;
