<?php
// fiscalizacion/includes/auth.php
// Funciones de autenticacion y control de sesion del modulo Fiscalizacion.
// Independiente de includes/auth.php de Consulta Padron.
// Se incluye en index.php y esta disponible para todos los modulos.
//
// Roles disponibles:
//   fiscal     — accede solo al modulo fiscal
//   admin      — accede a dashboard y listados
//   superadmin — accede a todo lo anterior mas abm_mesas y abm_usuarios

// verificar_sesion_fiscal()
// Si no hay sesion activa redirige al login.
// Llamar al inicio de todo modulo que requiera autenticacion.
function verificar_sesion_fiscal() {
    if (!isset($_SESSION['rol'])) {
        header('Location: index.php?mod=login');
        exit;
    }
}

// verificar_admin_fiscal()
// Si el usuario no es admin ni superadmin redirige al dashboard con error.
// Llamar al inicio de los modulos de administracion.
function verificar_admin_fiscal() {
    verificar_sesion_fiscal();
    if (!in_array($_SESSION['rol'], ['admin', 'superadmin'])) {
        header('Location: index.php?mod=dashboard&error=acceso_denegado');
        exit;
    }
}

// verificar_superadmin_fiscal()
// Si el usuario no es superadmin redirige al dashboard con error.
// Llamar al inicio de los modulos abm_mesas y abm_usuarios.
function verificar_superadmin_fiscal() {
    verificar_sesion_fiscal();
    if ($_SESSION['rol'] !== 'superadmin') {
        header('Location: index.php?mod=dashboard&error=acceso_denegado');
        exit;
    }
}
