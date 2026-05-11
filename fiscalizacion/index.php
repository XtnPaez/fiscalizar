<?php
// fiscalizacion/index.php
// Router principal del sistema Fiscalizar — Fiscalizacion.
// Recibe todos los requests y carga el modulo correspondiente segun ?mod=
// Sin sesion activa redirige al login.
// Sin parametro mod carga el login por defecto.
// Cualquier excepcion no manejada redirige al modulo error sin romper la sesion.

session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

// Modulos disponibles y sus archivos correspondientes
$modulos = [
    'login'         => 'modulos/login/login.php',
    'logout'        => 'modulos/logout/logout.php',
    'error'         => 'modulos/error/error.php',
    'fiscal'        => 'modulos/fiscal/fiscal.php',
    'dashboard'     => 'modulos/dashboard/dashboard.php',
    'listados'      => 'modulos/listados/listados.php',
    'abm_mesas'     => 'modulos/abm_mesas/abm_mesas.php',
    'abm_usuarios'  => 'modulos/abm_usuarios/abm_usuarios.php',
];

// Leer el parametro mod de la URL
$mod = isset($_GET['mod']) ? $_GET['mod'] : 'login';

// Si el modulo no existe redirigir al login
if (!array_key_exists($mod, $modulos)) {
    $mod = 'login';
}

// El login es el unico modulo publico
// Todos los demas requieren sesion activa
if ($mod !== 'login') {
    verificar_sesion_fiscal();
}

// Cargar el modulo dentro de un try/catch global.
// Cualquier excepcion no manejada redirige al modulo error sin romper la sesion.
try {
    require_once $modulos[$mod];
} catch (Exception $e) {
    $mensaje_error = 'El sistema encontro un problema. Intenta de nuevo.';
    require_once $modulos['error'];
    exit;
}
