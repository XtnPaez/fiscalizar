<?php
// includes/navbar.php
// Navbar fija superior del sistema Fiscalizar — Consulta Padron.
// Muestra solo los items a los que el usuario tiene acceso segun su nivel.
// Se incluye al inicio de cada modulo autenticado.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiscalizar — Consulta Padron</title>

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
        <a class="navbar-brand fw-semibold" href="index.php?mod=buscador">Fiscalizar</a>

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

            <!-- Links de navegacion — izquierda -->
            <ul class="navbar-nav me-auto mb-2 mb-md-0">

                <!-- Buscador: todos los niveles -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=buscador">Buscador</a>
                </li>

                <!-- Padrones: todos los niveles -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=padrones">Padrones</a>
                </li>

                <!-- Filtros: todos los niveles -->
                <li class="nav-item">
                    <a class="nav-link" href="index.php?mod=filtros">Filtros</a>
                </li>

                <?php if (in_array($_SESSION['nivel'], ['admin', 'superadmin'])): ?>

                <!-- Administracion: solo admin y superadmin -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        Administracion
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_referentes">Referentes</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_partidos">Partidos</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_trabajos">Trabajos</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_personas">Personas</a>
                        </li>

                        <?php if ($_SESSION['nivel'] === 'superadmin'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="index.php?mod=abm_usuarios">Usuarios</a>
                        </li>
                        <?php endif; ?>

                    </ul>
                </li>

                <?php endif; ?>

            </ul>

            <!-- Usuario logueado — derecha -->
            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($_SESSION['usuario'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="badge ms-1" style="background-color:#a6d900;color:#1a1a2e;font-size:0.7rem;">
                            <?php echo htmlspecialchars($_SESSION['nivel'], ENT_QUOTES, 'UTF-8'); ?>
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
