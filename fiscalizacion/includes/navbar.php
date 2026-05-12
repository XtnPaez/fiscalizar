<?php
// fiscalizacion/includes/navbar.php
// Navbar fija superior del modulo Fiscalizacion.
// Muestra items distintos segun el rol: fiscal, admin, superadmin.
//
// Estructura del menu por rol:
//   fiscal     — solo nombre de mesa y logout
//   admin      — Dashboard | Listados | Observados
//   superadmin — Dashboard | Listados | Observados | Administracion (dropdown)
//
// Administracion (solo superadmin):
//   - Elecciones (abm_elecciones): gestiona elecciones, dias y mesas
//   - Usuarios (abm_usuarios): gestiona usuarios fiscales
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiscalizar</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/logo.png">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Estilos propios -->
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-md navbar-dark sticky-top" style="background-color: #1a1a2e;">
    <div class="container">

        <!-- Nombre del sistema -->
        <a class="navbar-brand fw-semibold" href="index.php">Fiscalizar</a>

        <!-- Boton hamburguesa para mobile -->
        <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMenu"
            aria-controls="navbarMenu"
            aria-expanded="false"
            aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMenu">

            <?php if ($_SESSION['rol'] === 'fiscal'): ?>

            <!-- Fiscal: solo ve el nombre de su mesa, sin menu -->
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <span class="nav-link text-white-50">
                        Mesa: <?php echo htmlspecialchars($_SESSION['nombre_mesa'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </li>
            </ul>

            <?php else: ?>

            <!-- Admin y superadmin: menu completo -->
            <ul class="navbar-nav me-auto mb-2 mb-md-0">

                <!-- Dashboard: admin y superadmin -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=dashboard">Dashboard</a>
                </li>

                <!-- Listados: admin y superadmin -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=listados">Listados</a>
                </li>

                <!-- Observados: admin y superadmin -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=observados">Observados</a>
                </li>

                <?php if ($_SESSION['rol'] === 'superadmin'): ?>

                <!-- Administracion: solo superadmin -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Administracion
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_elecciones">Elecciones</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_usuarios">Usuarios</a>
                        </li>
                    </ul>
                </li>

                <?php endif; ?>

            </ul>

            <?php endif; ?>

            <!-- Usuario logueado — derecha -->
            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">

                        <?php if ($_SESSION['rol'] === 'fiscal'): ?>
                            <?php echo htmlspecialchars($_SESSION['nombre_mesa'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>

                        <span class="badge ms-1" style="background-color:#a6d900;color:#1a1a2e;font-size:0.7rem;">
                            <?php echo htmlspecialchars($_SESSION['rol'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="index.php?mod=logout">Cerrar sesion</a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div>
    </div>
</nav>

<!-- Contenedor principal — el modulo cierra este div antes del footer -->
<main class="flex-shrink-0">
    <div class="container mt-4">
