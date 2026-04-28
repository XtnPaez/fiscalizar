<?php
// modulos/login/login.php
// Modulo de login del sistema Fiscalizar — Consulta Padron.
// Acceso: publico.
// Si ya hay sesion activa redirige al buscador.
// Autentica contra la tabla usuarios de fiscaliz_padron.

// Si ya hay sesion activa no tiene sentido mostrar el login
if (isset($_SESSION['usuario'])) {
    header('Location: index.php?mod=buscador');
    exit;
}

$error = '';

// Procesar el formulario cuando llega por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($usuario === '' || $password === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {

        // Buscar el usuario en la base — solo usuarios activos
        $stmt = $pdo->prepare("
            SELECT id, usuario, password, nivel
            FROM usuarios
            WHERE usuario = :usuario
            AND activo = 1
        ");
        $stmt->execute([':usuario' => $usuario]);
        $fila = $stmt->fetch();

        if ($fila && password_verify($password, $fila['password'])) {
            // Credenciales correctas — iniciar sesion
            $_SESSION['usuario']    = $fila['usuario'];
            $_SESSION['id_usuario'] = $fila['id'];
            $_SESSION['nivel']      = $fila['nivel'];

            header('Location: index.php?mod=buscador');
            exit;
        } else {
            // Credenciales incorrectas — no revelar si el usuario existe o no
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiscalizar — Ingreso</title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Variables del sistema */
        :root {
            --color-fondo:      #f0f2f5;
            --color-navbar:     #1a1a2e;
            --color-acento:     #a6d900;
            --color-texto:      #1a1a2e;
            --color-secundario: #4a5568;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-fondo);
            color: var(--color-texto);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }

        .login-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 2.5rem 2rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .login-titulo {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--color-navbar);
            margin-bottom: 0.25rem;
        }

        .login-subtitulo {
            font-size: 0.85rem;
            color: var(--color-secundario);
            margin-bottom: 2rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-texto);
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
            padding: 0.6rem 0.85rem;
        }

        .form-control:focus {
            border-color: var(--color-acento);
            box-shadow: 0 0 0 3px rgba(166, 217, 0, 0.2);
        }

        .btn-ingresar {
            background-color: var(--color-acento);
            border: none;
            color: var(--color-navbar);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.65rem;
            border-radius: 6px;
            width: 100%;
            margin-top: 0.5rem;
            transition: opacity 0.15s ease;
        }

        .btn-ingresar:hover {
            opacity: 0.88;
            color: var(--color-navbar);
        }

        .alerta-error {
            font-size: 0.875rem;
            border-radius: 6px;
            padding: 0.65rem 1rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.78rem;
            color: var(--color-secundario);
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-titulo">Fiscalizar</div>
        <div class="login-subtitulo">Facultad de Ciencias Sociales — UBA</div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alerta-error" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <input
                    type="text"
                    class="form-control"
                    id="usuario"
                    name="usuario"
                    autocomplete="username"
                    autofocus
                    value="<?php echo htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-ingresar">Ingresar</button>

        </form>

    </div>

    <div class="login-footer">
        Consulta Padron &mdash; Fiscalizar
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
