<?php
// includes/auth.php
// Funciones de autenticacion y control de sesion.
// Se incluye en index.php y esta disponible para todos los modulos.

// verificar_sesion()
// Si no hay sesion activa redirige al login.
// Llamar al inicio de todo modulo que requiera autenticacion.
function verificar_sesion() {
    if (!isset($_SESSION['usuario'])) {
        header('Location: index.php?mod=login');
        exit;
    }
}

// verificar_admin()
// Si el usuario no es admin ni superadmin redirige al buscador con error.
// Llamar al inicio de los modulos ABM de catalogos y personas.
function verificar_admin() {
    verificar_sesion();
    if (!in_array($_SESSION['nivel'], ['admin', 'superadmin'])) {
        header('Location: index.php?mod=buscador&error=acceso_denegado');
        exit;
    }
}

// verificar_superadmin()
// Si el usuario no es superadmin redirige al buscador con error.
// Llamar al inicio del modulo abm_usuarios unicamente.
function verificar_superadmin() {
    verificar_sesion();
    if ($_SESSION['nivel'] !== 'superadmin') {
        header('Location: index.php?mod=buscador&error=acceso_denegado');
        exit;
    }
}
