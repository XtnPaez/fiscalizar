<?php
// fiscalizacion/modulos/logout/logout.php
// Cierre de sesion del sistema Fiscalizar — Fiscalizacion.
// Si el usuario era fiscal libera la mesa (en_uso = 0).
// Destruye la sesion y redirige al login.
// Acceso: todos los roles autenticados.

// Si era fiscal liberar la mesa
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'fiscal' && isset($_SESSION['id_mesa'])) {
    $pdo->prepare("UPDATE mesas SET en_uso = 0 WHERE id = ?")
        ->execute([$_SESSION['id_mesa']]);
}

// Destruir sesion
session_destroy();

header('Location: index.php?mod=login');
exit;
