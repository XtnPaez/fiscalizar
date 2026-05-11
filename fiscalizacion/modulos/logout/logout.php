<?php
// fiscalizacion/modulos/logout/logout.php
// Cierre de sesion del sistema Fiscalizar — Fiscalizacion.
// Si el usuario era fiscal libera la mesa (en_uso = 0) y muestra pantalla de gracias.
// Si es admin o superadmin destruye la sesion y redirige al login directamente.
// Acceso: todos los roles autenticados.

$es_fiscal = isset($_SESSION['rol']) && $_SESSION['rol'] === 'fiscal';

// Si era fiscal liberar la mesa
if ($es_fiscal && isset($_SESSION['id_mesa'])) {
    $pdo->prepare("UPDATE mesas SET en_uso = 0 WHERE id = ?")
        ->execute([$_SESSION['id_mesa']]);
}

// Destruir sesion
session_destroy();

// Admin y superadmin van directo al login
if (!$es_fiscal) {
    header('Location: index.php?mod=login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiscalizar — Hasta luego</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<div class="d-flex flex-column align-items-center justify-content-center"
    style="min-height:100vh;text-align:center;padding:2rem;">

    <div style="font-size:4rem;margin-bottom:1rem;">🗳️</div>

    <h2 class="fw-bold mb-2" style="color:#1a1a2e;">¡Gracias totales!</h2>

    <p class="text-secondary mb-4" style="font-size:1rem;max-width:320px;">
        Tu trabajo hace posible una elección transparente.<br>
        La democracia te lo agradece.
    </p>

    <a href="index.php" class="btn btn-acento">Volver al inicio</a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Redirigir automaticamente despues de 4 segundos -->
<script>
    setTimeout(function () {
        window.location.href = 'index.php';
    }, 4000);
</script>

</body>
</html>
