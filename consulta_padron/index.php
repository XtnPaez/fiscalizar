<?php
// index.php
// Router principal del sistema Fiscalizar — Consulta Padron.
// Recibe todos los requests y carga el modulo correspondiente segun ?mod=
// Sin sesion activa redirige al login.
// Sin parametro mod carga el buscador por defecto.

session_start();

require_once 'config/db.php';
require_once 'includes/auth.php';

// Modulos disponibles y sus archivos correspondientes
$modulos = [
    'login'          => 'modulos/login/login.php',
    'logout'         => 'modulos/logout/logout.php',
    'buscador'       => 'modulos/buscador/buscador.php',
    'padrones'       => 'modulos/padrones/padrones.php',
    'filtros'        => 'modulos/filtros/filtros.php',
    'abm_referentes' => 'modulos/abm_referentes/abm_referentes.php',
    'abm_partidos'   => 'modulos/abm_partidos/abm_partidos.php',
    'abm_trabajos'   => 'modulos/abm_trabajos/abm_trabajos.php',
    'abm_personas'   => 'modulos/abm_personas/abm_personas.php',
    'abm_usuarios'   => 'modulos/abm_usuarios/abm_usuarios.php',
];

// Leer el parametro mod de la URL
$mod = isset($_GET['mod']) ? $_GET['mod'] : 'buscador';

// Si el modulo no existe redirigir al buscador
if (!array_key_exists($mod, $modulos)) {
    $mod = 'buscador';
}

// El login es el unico modulo publico
// Todos los demas requieren sesion activa
if ($mod !== 'login') {
    verificar_sesion();
}

// Cargar el modulo correspondiente
require_once $modulos[$mod];
